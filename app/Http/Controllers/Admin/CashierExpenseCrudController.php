<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CashierExpenseRequest;
use App\Models\CashierExpense;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class CashierExpenseCrudController extends CrudController
{
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
            'name' => 'category',
            'label' => 'Category',
            'type' => 'text',
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
            'name' => 'category',
            'type' => 'select2',
            'label' => 'Category',
        ], function () {
            return CashierExpense::categories();
        }, function ($value) {
            $this->crud->addClause('where', 'category', $value);
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
            'name' => 'category',
            'label' => 'Category',
            'type' => 'select_from_array',
            'options' => CashierExpense::categories(),
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
    }
}
