<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CashierExpenseRequest;
use App\Models\CashierExpense;
use App\Models\ExpenseCategory;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;

class CashierExpenseCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(CashierExpense::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/cashier-expense');
        CRUD::setEntityNameStrings('expense', 'expenses');

        $this->crud->enableExportButtons();
    }

    protected function setupListOperation(): void
    {
        $this->crud->query->with('category');
        $this->addExpenseStatsWidget();

        CRUD::addColumn([
            'name' => 'id',
            'label' => 'ID',
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'type',
            'label' => 'Type',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'category_id',
            'label' => 'Category',
            'type' => 'select',
            'entity' => 'category',
            'attribute' => 'name',
            'model' => ExpenseCategory::class,
        ]);

        CRUD::addColumn([
            'name' => 'amount_gel',
            'label' => 'Amount',
            'type' => 'number',
            'decimals' => 2,
            'suffix' => ' ₾',
        ]);

        CRUD::addColumn([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'text',
            'limit' => 80,
        ]);

        CRUD::addColumn([
            'name' => 'expense_date',
            'label' => 'Date',
            'type' => 'datetime',
        ]);

        CRUD::addFilter([
            'name' => 'type',
            'type' => 'select2',
            'label' => 'Type',
        ], function () {
            return CashierExpense::types();
        }, function ($value) {
            $this->crud->addClause('where', 'type', $value);
        });

        CRUD::addFilter([
            'name' => 'category_id',
            'type' => 'select2',
            'label' => 'Category',
        ], function () {
            return ExpenseCategory::filterOptions();
        }, function ($value) {
            $this->applyCategoryFilter($this->crud->query, (int) $value);
        });

        CRUD::addFilter([
            'type' => 'date_range',
            'name' => 'expense_date',
            'label' => 'Date Range',
        ], false, function ($value) {
            $dates = json_decode($value, true);
            if (!empty($dates['from'])) {
                $this->crud->addClause('where', 'expense_date', '>=', \Carbon\Carbon::parse($dates['from'])->startOfDay());
            }
            if (!empty($dates['to'])) {
                $this->crud->addClause('where', 'expense_date', '<=', \Carbon\Carbon::parse($dates['to'])->endOfDay());
            }
        });

        $this->crud->orderBy('expense_date', 'desc');
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(CashierExpenseRequest::class);

        CRUD::addField([
            'name' => 'type',
            'label' => 'Type',
            'type' => 'select_from_array',
            'options' => CashierExpense::types(),
            'allows_null' => false,
            'default' => CashierExpense::TYPE_CASH,
        ]);

        CRUD::addField([
            'name' => 'category_id',
            'label' => 'Category',
            'type' => 'select_optgroup_array',
            'options' => ExpenseCategory::groupedLeafOptions(),
            'allows_null' => false,
        ]);

        CRUD::addField([
            'name' => 'amount_gel',
            'label' => 'Amount (GEL)',
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0.01',
                'required' => true,
            ],
            'suffix' => '₾',
        ]);

        CRUD::addField([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'textarea',
        ]);

        CRUD::addField([
            'name' => 'expense_date',
            'label' => 'Date',
            'type' => 'datetime_picker',
            'default' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();

        // Keep the current category visible if it stopped being a leaf after
        // children were added under it; validation still requires a leaf.
        $entry = $this->crud->getCurrentEntry();
        if ($entry && $entry->category_id && $entry->category) {
            $options = ExpenseCategory::groupedLeafOptions();
            $flat = [];
            foreach ($options as $items) {
                $flat += $items;
            }
            if (! array_key_exists($entry->category_id, $flat)) {
                $options[''][$entry->category_id] = $entry->category->name . ' (has children — pick a leaf)';
                CRUD::modifyField('category_id', ['options' => $options]);
            }
        }
    }

    /**
     * Include expenses whose category is the selected node or any descendant.
     */
    protected function applyCategoryFilter($query, int $categoryId)
    {
        $category = ExpenseCategory::find($categoryId);
        if (! $category) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('category', function ($q) use ($category) {
            $q->where('lft', '>=', $category->lft)
                ->where('rgt', '<=', $category->rgt);
        });
    }

    /**
     * Apply the list filters (type, category, date range) to a query so the
     * summary widget totals match exactly what the filtered table shows.
     */
    protected function applyExpenseFilters($query)
    {
        if (request()->filled('type')) {
            $query->where('type', request()->get('type'));
        }

        if (request()->filled('category_id')) {
            $this->applyCategoryFilter($query, (int) request()->get('category_id'));
        }

        if (request()->filled('expense_date')) {
            $dates = json_decode(request()->get('expense_date'), true);
            if (is_array($dates)) {
                if (!empty($dates['from'])) {
                    $query->where('expense_date', '>=', \Carbon\Carbon::parse($dates['from'])->startOfDay());
                }
                if (!empty($dates['to'])) {
                    $query->where('expense_date', '<=', \Carbon\Carbon::parse($dates['to'])->endOfDay());
                }
            }
        }

        return $query;
    }

    /**
     * Compute the filter-aware summary totals.
     */
    protected function calculateExpenseStats(): array
    {
        $query = $this->applyExpenseFilters(CashierExpense::query());

        return [
            'expensesCount' => (clone $query)->count(),
            'totalAmount' => (float) (clone $query)->sum('amount_gel'),
            'totalCash' => (float) (clone $query)->where('type', CashierExpense::TYPE_CASH)->sum('amount_gel'),
            'totalTransfer' => (float) (clone $query)->where('type', CashierExpense::TYPE_TRANSFER)->sum('amount_gel'),
        ];
    }

    /**
     * Add a summary widget totaling expenses for the current list filters.
     */
    protected function addExpenseStatsWidget(): void
    {
        Widget::add(array_merge([
            'type' => 'view',
            'view' => 'vendor.backpack.crud.widgets.cashier_expense_stats',
            'wrapper' => ['class' => 'col-12'],
        ], $this->calculateExpenseStats()))->to('before_content');
    }

    /**
     * Return the filter-aware summary totals as JSON (for live widget updates).
     */
    public function getExpenseStats(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->calculateExpenseStats());
    }
}
