<?php

namespace App\Http\Controllers\Admin;

use App\Models\CashierExpense;
use App\Models\Supplier;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;

/**
 * Class SupplierBalanceCrudController
 *
 * Read-only list of suppliers with balances computed live from their
 * confirmed Expenses-Purchases (cashier_expenses) rows - drafts are still
 * being entered and count nowhere:
 *   - Total Amount = SUM(amount_gel + credit) — everything purchased from them
 *   - Total Paid   = SUM(amount_gel)          — what has actually been paid
 *   - Total Credit = SUM(credit)              — remaining unpaid amount
 *   - Balance      = Total Paid - Total Credit
 *
 * amount_gel is the paid portion; credit is independent (a fully-on-credit
 * purchase is amount 0, credit 1000). Repayments lower the credit field.
 *
 * The period filter limits every total to confirmed expenses whose
 * expense_date falls in that range, so a month shows the debt recorded
 * on that month's purchases.
 *
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class SupplierBalanceCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;

    protected bool $expensePeriodResolved = false;

    protected ?\Carbon\Carbon $expensePeriodFrom = null;

    protected ?\Carbon\Carbon $expensePeriodTo = null;

    public function setup(): void
    {
        CRUD::setModel(Supplier::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/supplier-balance');
        CRUD::setEntityNameStrings(__('supplier_balance.entity'), __('supplier_balance.entity_plural'));

        // Read-only screen
        $this->crud->denyAccess(['create', 'update', 'delete', 'show']);
        $this->crud->enableExportButtons();
    }

    protected function setupListOperation(): void
    {
        // Totals across every supplier the current filters match (not just the
        // page being shown).
        $this->addBalanceStatsWidget();

        // Aggregate the expense sums in the main query so the list stays a
        // single query (no N+1) and the columns can be sorted server-side.
        // The closure applies the period filter when one is selected.
        $this->crud->addClause('withSum', [
            'confirmedCashierExpenses as paid_sum' => function ($query) {
                $this->constrainExpensesToPeriod($query);
            },
        ], 'amount_gel');
        $this->crud->addClause('withSum', [
            'confirmedCashierExpenses as credit_total' => function ($query) {
                $this->constrainExpensesToPeriod($query);
            },
        ], 'credit');

        // Expandable rows: clicking a supplier row loads their expenses inline.
        // The custom list view makes the whole row (not just the +/- icon) the trigger.
        $this->crud->enableDetailsRow();
        $this->crud->setDetailsRowView('vendor.backpack.crud.details_rows.supplier_balance');
        $this->crud->setListView('vendor.backpack.crud.supplier_balance.list');

        CRUD::addColumn([
            'name' => 'id',
            'label' => __('supplier_balance.id'),
            'type' => 'number',
            'searchLogic' => false,
        ]);

        CRUD::addColumn([
            'name' => 'name',
            'label' => __('supplier_balance.supplier'),
            'type' => 'text',
            'limit' => 9999,
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('name', 'like', '%' . $searchTerm . '%');
            },
        ]);

        CRUD::addColumn([
            'name' => 'phone',
            'label' => __('supplier_balance.phone'),
            'type' => 'phone',
            'searchLogic' => false,
        ]);

        CRUD::addColumn([
            'name' => 'email',
            'label' => __('supplier_balance.email'),
            'type' => 'email',
            'searchLogic' => false,
        ]);

        // ₾ stays in the column titles only so Excel export cells are plain numbers.
        CRUD::addColumn([
            'name' => 'expenses_total',
            'label' => __('supplier_balance.expenses_total'),
            'type' => 'number',
            'decimals' => 2,
            'searchLogic' => false,
            'orderable' => true,
            'orderLogic' => function ($query, $column, $columnDirection) {
                return $query->orderByRaw(
                    $this->confirmedExpensesSumSql('amount_gel + credit')
                    . ' ' . $this->sqlDirection($columnDirection)
                );
            },
            'value' => fn ($entry) => $this->expensesTotalFor($entry),
        ]);

        CRUD::addColumn([
            'name' => 'paid_total',
            'label' => __('supplier_balance.paid_total'),
            'type' => 'number',
            'decimals' => 2,
            'searchLogic' => false,
            'orderable' => true,
            // Postgres does not allow select aliases inside ORDER BY expressions,
            // so the paid amount is recomputed as a subquery for sorting.
            'orderLogic' => function ($query, $column, $columnDirection) {
                return $query->orderByRaw(
                    $this->confirmedExpensesSumSql('amount_gel')
                    . ' ' . $this->sqlDirection($columnDirection)
                );
            },
            'value' => fn ($entry) => $this->paidTotalFor($entry),
        ]);

        CRUD::addColumn([
            'name' => 'credit_total',
            'label' => __('supplier_balance.credit_total'),
            'type' => 'number',
            'decimals' => 2,
            'searchLogic' => false,
            'orderable' => true,
            'orderLogic' => function ($query, $column, $columnDirection) {
                return $query->orderByRaw('credit_total ' . $this->sqlDirection($columnDirection) . ' NULLS LAST');
            },
            'value' => fn ($entry) => (float) $entry->credit_total,
            'wrapper' => [
                'element' => 'span',
                'class' => function ($crud, $column, $entry, $related_key) {
                    return ((float) $entry->credit_total) > 0 ? 'text-danger fw-bold' : 'text-secondary';
                },
            ],
        ]);

        CRUD::addColumn([
            'name' => 'balance',
            'label' => __('supplier_balance.balance'),
            'type' => 'number',
            'decimals' => 2,
            'searchLogic' => false,
            'orderable' => true,
            // Postgres does not allow select aliases inside ORDER BY expressions,
            // so the signed balance is recomputed as a subquery for sorting.
            'orderLogic' => function ($query, $column, $columnDirection) {
                return $query->orderByRaw(
                    $this->balanceSubquerySql() . ' ' . $this->sqlDirection($columnDirection)
                );
            },
            'value' => fn ($entry) => $this->balanceFor($entry),
            'wrapper' => [
                'element' => 'span',
                'class' => function ($crud, $column, $entry, $related_key) {
                    return $this->balanceFor($entry) < 0 ? 'text-danger fw-bold' : 'text-success';
                },
            ],
        ]);

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'name',
            'label' => __('supplier_balance.filters.name'),
        ], false, function ($value) {
            $this->crud->addClause('where', 'name', 'LIKE', "%{$value}%");
        });

        // Month (or any range). Presets include this month and last month.
        // Sums above already read the same request value; this clause only
        // hides suppliers with no confirmed expenses in the range.
        CRUD::addFilter([
            'type' => 'date_range',
            'name' => 'expense_date',
            'label' => __('supplier_balance.filters.period'),
        ], false, function () {
            if (! $this->hasExpensePeriod()) {
                return;
            }

            $this->crud->addClause('whereHas', 'confirmedCashierExpenses', function ($query) {
                $this->constrainExpensesToPeriod($query);
            });
        });

        CRUD::addFilter([
            'name' => 'only_debt',
            'type' => 'simple',
            'label' => __('supplier_balance.filters.only_debt'),
        ], false, function () {
            $this->crud->addClause('whereHas', 'confirmedCashierExpenses', function ($query) {
                $this->constrainExpensesToPeriod($query);
                $query->where('credit', '>', 0);
            });
        });

        // Most negative balances first (outstanding credit exceeds what we paid).
        // No bindings here: Backpack counts with a subquery that strips ORDER BY
        // and would otherwise leave a dangling ? parameter.
        $this->crud->addClause('orderByRaw', $this->balanceSubquerySql() . ' ASC');
    }

    /**
     * Suppliers matching the current list filters. Kept in sync with the filters
     * registered in setupListOperation (plus the search box, which searches the
     * name column) so the widget totals describe exactly the rows being listed.
     */
    protected function filteredSuppliersQuery()
    {
        $query = Supplier::query();

        if (request()->filled('name')) {
            $query->where('name', 'LIKE', '%' . request()->get('name') . '%');
        }

        if (request()->filled('search')) {
            $query->where('name', 'LIKE', '%' . request()->get('search') . '%');
        }

        if (request()->filled('only_debt') || $this->hasExpensePeriod()) {
            $query->whereHas('confirmedCashierExpenses', function ($subQuery) {
                $this->constrainExpensesToPeriod($subQuery);

                if (request()->filled('only_debt')) {
                    $subQuery->where('credit', '>', 0);
                }
            });
        }

        return $query;
    }

    /**
     * Widget totals over the filtered suppliers: what has actually been paid to
     * them, and the summed balance (paid - remaining credit). Aggregated in SQL
     * so this stays one query however many suppliers match.
     *
     * @return array{paidTotal:float, creditTotal:float, balanceTotal:float}
     */
    protected function calculateBalanceStats(): array
    {
        $totals = $this->filteredSuppliersQuery()
            ->selectRaw(
                'COALESCE(SUM(' . $this->confirmedExpensesSumSql('amount_gel') . '), 0) AS paid_total,'
                . ' COALESCE(SUM(' . $this->confirmedExpensesSumSql('credit') . '), 0) AS credit_total'
            )
            ->first();

        $paidTotal = (float) ($totals->paid_total ?? 0);
        $creditTotal = (float) ($totals->credit_total ?? 0);

        return [
            'paidTotal' => $paidTotal,
            'creditTotal' => $creditTotal,
            'balanceTotal' => $paidTotal - $creditTotal,
        ];
    }

    /**
     * Add the summary widget above the list.
     */
    protected function addBalanceStatsWidget(): void
    {
        // The DataTables /search endpoint and the details-row route run
        // setupListOperation() too, but render no widgets — skip the totals
        // query there. The widget follows the list via getBalanceStats instead.
        if (request()->ajax()) {
            return;
        }

        Widget::add(array_merge([
            'type' => 'view',
            'view' => 'vendor.backpack.crud.widgets.supplier_balance_stats',
            'wrapper' => ['class' => 'col-12'],
        ], $this->calculateBalanceStats()))->to('before_content');
    }

    /**
     * Return the filter-aware totals as JSON, so the widget can follow the list
     * without a page reload.
     */
    public function getBalanceStats(): \Illuminate\Http\JsonResponse
    {
        $this->crud->hasAccessOrFail('list');

        return response()->json($this->calculateBalanceStats());
    }

    /**
     * Whitelist the DataTables sort direction for use in raw ORDER BY SQL.
     */
    protected function sqlDirection(string $direction): string
    {
        return strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
    }

    /**
     * Render the expanded sub-list for one supplier row: their expenses with
     * amount / paid / credit per row. Called over AJAX by the details-row logic.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function showDetailsRow($id)
    {
        $this->crud->hasAccessOrFail('list');

        $supplier = Supplier::query()
            ->with([
                'confirmedCashierExpenses' => function ($query) {
                    $this->constrainExpensesToPeriod($query);
                    $query->orderByDesc('expense_date')->orderByDesc('id');
                },
                'confirmedCashierExpenses.category',
                'confirmedCashierExpenses.product',
            ])
            ->findOrFail($id);

        $paidTotal = (float) $supplier->confirmedCashierExpenses->sum('amount_gel');
        $creditTotal = (float) $supplier->confirmedCashierExpenses->sum('credit');

        $expensesUrl = url(config('backpack.base.route_prefix') . '/cashier-expense')
            . '?supplier_id=' . $supplier->id;
        if ($period = $this->expenseDateFilterValue()) {
            $expensesUrl .= '&expense_date=' . urlencode($period);
        }

        return view('vendor.backpack.crud.details_rows.supplier_balance', [
            'crud' => $this->crud,
            'entry' => $supplier,
            'expenses' => $supplier->confirmedCashierExpenses,
            'expensesTotal' => $paidTotal + $creditTotal,
            'paidTotal' => $paidTotal,
            'creditTotal' => $creditTotal,
            'balance' => $paidTotal - $creditTotal,
            'periodLabel' => $this->expensePeriodLabel(),
            'expensesUrl' => $expensesUrl,
        ]);
    }

    /**
     * Full purchase total: paid + remaining credit.
     */
    protected function expensesTotalFor(Supplier $entry): float
    {
        return $this->paidTotalFor($entry) + (float) $entry->credit_total;
    }

    /**
     * Amount actually paid to this supplier: SUM(amount_gel).
     */
    protected function paidTotalFor(Supplier $entry): float
    {
        return (float) $entry->paid_sum;
    }

    /**
     * Real balance: paid sum minus remaining credit sum.
     */
    protected function balanceFor(Supplier $entry): float
    {
        return $this->paidTotalFor($entry) - (float) $entry->credit_total;
    }

    /**
     * SQL expression for the signed balance, used in ORDER BY (paid - credit).
     */
    protected function balanceSubquerySql(): string
    {
        return $this->confirmedExpensesSumSql('amount_gel - credit');
    }

    /**
     * Confirmed-expense SUM() subquery, used in ORDER BY. Status is inlined
     * (not bound): Backpack's count query drops ORDER BY but can leave its
     * bindings, which breaks pagination.
     */
    protected function confirmedExpensesSumSql(string $expression): string
    {
        return '(SELECT COALESCE(SUM(' . $expression . '), 0) FROM cashier_expenses'
            . ' WHERE cashier_expenses.supplier_id = suppliers.id'
            . ' AND cashier_expenses.status = ' . $this->confirmedStatusSql()
            . $this->expensePeriodSql() . ')';
    }

    /**
     * True when the period filter parsed to at least one bound.
     */
    protected function hasExpensePeriod(): bool
    {
        [$from, $to] = $this->selectedExpensePeriod();

        return $from !== null || $to !== null;
    }

    /**
     * Limit an expense query to the selected period. No-op when the filter is off,
     * so the list stays all-time until a range is chosen.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    protected function constrainExpensesToPeriod($query): void
    {
        [$from, $to] = $this->selectedExpensePeriod();

        if ($from) {
            $query->where('expense_date', '>=', $from);
        }

        if ($to) {
            $query->where('expense_date', '<=', $to);
        }
    }

    /**
     * Date fragment for raw SUM subqueries. Dates are reformatted from a
     * validated timestamp, not interpolated from the request, because these
     * clauses cannot use bindings (Backpack's count query drops ORDER BY
     * and would leave a dangling parameter).
     */
    protected function expensePeriodSql(): string
    {
        [$from, $to] = $this->selectedExpensePeriod();
        $sql = '';

        if ($from) {
            $sql .= " AND cashier_expenses.expense_date >= '" . $from->format('Y-m-d H:i:s') . "'";
        }

        if ($to) {
            $sql .= " AND cashier_expenses.expense_date <= '" . $to->format('Y-m-d H:i:s') . "'";
        }

        return $sql;
    }

    /**
     * @return array{0:?\Carbon\Carbon, 1:?\Carbon\Carbon}
     */
    protected function selectedExpensePeriod(): array
    {
        if ($this->expensePeriodResolved) {
            return [$this->expensePeriodFrom, $this->expensePeriodTo];
        }

        $this->expensePeriodResolved = true;
        $raw = request()->get('expense_date');

        if (! is_string($raw) || $raw === '') {
            return [null, null];
        }

        $dates = json_decode($raw, true);
        if (! is_array($dates)) {
            return [null, null];
        }

        $this->expensePeriodFrom = $this->periodBound($dates['from'] ?? null, true);
        $this->expensePeriodTo = $this->periodBound($dates['to'] ?? null, false);

        return [$this->expensePeriodFrom, $this->expensePeriodTo];
    }

    /**
     * JSON value the cashier-expense date filter understands, or null when
     * this list is not filtered by period.
     */
    protected function expenseDateFilterValue(): ?string
    {
        [$from, $to] = $this->selectedExpensePeriod();

        if (! $from && ! $to) {
            return null;
        }

        return json_encode([
            'from' => $from?->format('Y-m-d H:i:s'),
            'to' => $to?->format('Y-m-d H:i:s'),
        ]);
    }

    protected function expensePeriodLabel(): ?string
    {
        [$from, $to] = $this->selectedExpensePeriod();

        if (! $from && ! $to) {
            return null;
        }

        $fromLabel = $from ? $from->format('d M Y') : '…';
        $toLabel = $to ? $to->format('d M Y') : '…';

        return $fromLabel . ' – ' . $toLabel;
    }

    /**
     * Accept only a calendar date (optional time) and snap it to the day's edge.
     */
    protected function periodBound(mixed $value, bool $start): ?\Carbon\Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2})?$/', $value)) {
            return null;
        }

        try {
            $date = \Carbon\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        return $start ? $date->startOfDay() : $date->setTime(23, 59, 59);
    }

    /**
     * Quoted confirmed-status literal for raw SQL. STATUS_CONFIRMED is a
     * code constant, not user input; quoting avoids PDO bindings that
     * Backpack's pagination count query cannot keep in sync with ORDER BY.
     */
    protected function confirmedStatusSql(): string
    {
        return "'" . str_replace("'", "''", CashierExpense::STATUS_CONFIRMED) . "'";
    }
}
