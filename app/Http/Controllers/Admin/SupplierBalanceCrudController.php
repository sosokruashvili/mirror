<?php

namespace App\Http\Controllers\Admin;

use App\Models\CashierExpense;
use App\Models\Supplier;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

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
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class SupplierBalanceCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;

    public function setup(): void
    {
        CRUD::setModel(Supplier::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/supplier-balance');
        CRUD::setEntityNameStrings(__('supplier_balance.entity'), __('supplier_balance.entity_plural'));

        // Read-only screen
        $this->crud->denyAccess(['create', 'update', 'delete', 'show']);
    }

    protected function setupListOperation(): void
    {
        // Aggregate the expense sums in the main query so the list stays a
        // single query (no N+1) and the columns can be sorted server-side.
        $this->crud->addClause('withSum', 'confirmedCashierExpenses as paid_sum', 'amount_gel');
        $this->crud->addClause('withSum', 'confirmedCashierExpenses as credit_total', 'credit');

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

        CRUD::addFilter([
            'name' => 'only_debt',
            'type' => 'simple',
            'label' => __('supplier_balance.filters.only_debt'),
        ], false, function () {
            $this->crud->addClause('whereHas', 'confirmedCashierExpenses', function ($query) {
                $query->where('credit', '>', 0);
            });
        });

        // Most negative balances first (outstanding credit exceeds what we paid).
        // No bindings here: Backpack counts with a subquery that strips ORDER BY
        // and would otherwise leave a dangling ? parameter.
        $this->crud->addClause('orderByRaw', $this->balanceSubquerySql() . ' ASC');
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
                    $query->orderByDesc('expense_date')->orderByDesc('id');
                },
                'confirmedCashierExpenses.category',
                'confirmedCashierExpenses.product',
            ])
            ->findOrFail($id);

        $paidTotal = (float) $supplier->confirmedCashierExpenses->sum('amount_gel');
        $creditTotal = (float) $supplier->confirmedCashierExpenses->sum('credit');

        return view('vendor.backpack.crud.details_rows.supplier_balance', [
            'crud' => $this->crud,
            'entry' => $supplier,
            'expenses' => $supplier->confirmedCashierExpenses,
            'expensesTotal' => $paidTotal + $creditTotal,
            'paidTotal' => $paidTotal,
            'creditTotal' => $creditTotal,
            'balance' => $paidTotal - $creditTotal,
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
            . ' AND cashier_expenses.status = ' . $this->confirmedStatusSql() . ')';
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
