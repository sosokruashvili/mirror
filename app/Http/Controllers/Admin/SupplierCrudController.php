<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SupplierRequest;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class SupplierCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class SupplierCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(Supplier::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/supplier');
        CRUD::setEntityNameStrings('supplier', 'suppliers');

        $this->crud->enableExportButtons();
    }

    protected function setupListOperation(): void
    {
        $this->crud->orderBy('id', 'desc');

        CRUD::addColumn([
            'name' => 'id',
            'label' => 'ID',
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'text',
            'limit' => 80,
        ]);

        CRUD::addColumn([
            'name' => 'email',
            'label' => 'Email',
            'type' => 'email',
        ]);

        CRUD::addColumn([
            'name' => 'phone',
            'label' => 'Phone',
            'type' => 'phone',
        ]);

        CRUD::addColumn([
            'name' => 'address',
            'label' => 'Address',
            'type' => 'text',
            'limit' => 60,
        ]);

        CRUD::addColumn([
            'name' => 'legal_id',
            'label' => 'Legal ID',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'expenseCategories',
            'label' => 'Expense categories',
            'type' => 'select_multiple',
            'entity' => 'expenseCategories',
            'attribute' => 'name',
            'model' => ExpenseCategory::class,
        ]);
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(SupplierRequest::class);

        CRUD::addField([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
            'attributes' => ['required' => true],
        ]);

        CRUD::addField([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'textarea',
        ]);

        CRUD::addField([
            'name' => 'email',
            'label' => 'Email',
            'type' => 'email',
        ]);

        CRUD::addField([
            'name' => 'address',
            'label' => 'Address',
            'type' => 'textarea',
        ]);

        CRUD::addField([
            'name' => 'phone',
            'label' => 'Phone',
            'type' => 'phone',
        ]);

        CRUD::addField([
            'name' => 'legal_id',
            'label' => 'Legal ID',
            'type' => 'text',
        ]);

        CRUD::addField([
            'name' => 'expenseCategories',
            'label' => 'Expense categories',
            'type' => 'select_multiple',
            'entity' => 'expenseCategories',
            'attribute' => 'select_label',
            'model' => ExpenseCategory::class,
            'pivot' => true,
            'options' => (function ($query) {
                return $query->orderBy('lft')->orderBy('id')->get();
            }),
            'attributes' => [
                'size' => 30,
            ],
            'hint' => 'Select one or more expense categories this supplier covers.',
        ]);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation(): void
    {
        $this->setupListOperation();
    }
}
