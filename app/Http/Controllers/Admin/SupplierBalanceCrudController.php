<?php

namespace App\Http\Controllers\Admin;

use App\Models\Supplier;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class SupplierBalanceCrudController
 *
 * Read-only list of suppliers with balances computed live from their
 * Expenses-Purchases (cashier_expenses) rows:
 *   - Total Amount = SUM(amount_gel)      — everything purchased from them
 *   - Total Paid   = SUM(amount - credit) — what has actually been paid
 *   - Balance      = SUM(credit)          — the outstanding debt we owe them
 *
 * Repayments are recorded by editing the original expense's credit field down,
 * so summing the current credit values is always accurate — no snapshots needed.
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
        $this->crud->addClause('withSum', 'cashierExpenses as expenses_total', 'amount_gel');
        $this->crud->addClause('withSum', 'cashierExpenses as credit_total', 'credit');

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
                return $query->orderByRaw('expenses_total ' . $this->sqlDirection($columnDirection) . ' NULLS LAST');
            },
            'value' => fn ($entry) => (float) $entry->expenses_total,
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
                    '(SELECT COALESCE(SUM(amount_gel - credit), 0) FROM cashier_expenses'
                    . ' WHERE cashier_expenses.supplier_id = suppliers.id) '
                    . $this->sqlDirection($columnDirection)
                );
            },
            'value' => fn ($entry) => (float) $entry->expenses_total - (float) $entry->credit_total,
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
                    return ((float) $entry->credit_total) > 0 ? 'text-danger fw-bold' : 'text-success';
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
            $this->crud->addClause('whereHas', 'cashierExpenses', function ($query) {
                $query->where('credit', '>', 0);
            });
        });

        // Suppliers we owe the most, first. NULLS LAST keeps suppliers without
        // any expenses at the bottom (Postgres puts NULLs first on DESC).
        $this->crud->addClause('orderByRaw', 'credit_total DESC NULLS LAST');
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
                'cashierExpenses' => function ($query) {
                    $query->orderByDesc('expense_date')->orderByDesc('id');
                },
                'cashierExpenses.category',
                'cashierExpenses.product',
            ])
            ->findOrFail($id);

        $expensesTotal = (float) $supplier->cashierExpenses->sum('amount_gel');
        $creditTotal = (float) $supplier->cashierExpenses->sum('credit');

        return view('vendor.backpack.crud.details_rows.supplier_balance', [
            'crud' => $this->crud,
            'entry' => $supplier,
            'expenses' => $supplier->cashierExpenses,
            'expensesTotal' => $expensesTotal,
            'paidTotal' => $expensesTotal - $creditTotal,
            'creditTotal' => $creditTotal,
        ]);
    }
}
