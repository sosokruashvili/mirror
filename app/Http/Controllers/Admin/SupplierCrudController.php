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
        CRUD::setEntityNameStrings(__('supplier.entity'), __('supplier.entity_plural'));

        $this->crud->enableExportButtons();
    }

    protected function setupListOperation(): void
    {
        $this->crud->orderBy('id', 'desc');

        CRUD::addColumn([
            'name' => 'id',
            'label' => __('supplier.id'),
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'name',
            'label' => __('supplier.name'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'description',
            'label' => __('supplier.description'),
            'type' => 'text',
            'limit' => 80,
        ]);

        CRUD::addColumn([
            'name' => 'email',
            'label' => __('supplier.email'),
            'type' => 'email',
        ]);

        CRUD::addColumn([
            'name' => 'phone',
            'label' => __('supplier.phone'),
            'type' => 'phone',
        ]);

        CRUD::addColumn([
            'name' => 'address',
            'label' => __('supplier.address'),
            'type' => 'text',
            'limit' => 60,
        ]);

        CRUD::addColumn([
            'name' => 'legal_id',
            'label' => __('supplier.legal_id'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'expenseCategories',
            'label' => __('supplier.expense_categories'),
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
            'label' => __('supplier.name'),
            'type' => 'text',
            'attributes' => ['required' => true],
        ]);

        CRUD::addField([
            'name' => 'description',
            'label' => __('supplier.description'),
            'type' => 'textarea',
        ]);

        CRUD::addField([
            'name' => 'email',
            'label' => __('supplier.email'),
            'type' => 'email',
        ]);

        CRUD::addField([
            'name' => 'address',
            'label' => __('supplier.address'),
            'type' => 'textarea',
        ]);

        CRUD::addField([
            'name' => 'phone',
            'label' => __('supplier.phone'),
            'type' => 'phone',
        ]);

        CRUD::addField([
            'name' => 'legal_id',
            'label' => __('supplier.legal_id'),
            'type' => 'text',
        ]);

        CRUD::addField([
            'name' => 'expenseCategories',
            'label' => __('supplier.expense_categories'),
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
            'hint' => __('supplier.hints.expense_categories'),
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
