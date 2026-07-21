<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PurchaseRequest;
use App\Models\Purchase;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class PurchaseCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PurchaseCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(Purchase::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/purchase');
        CRUD::setEntityNameStrings('purchase', 'purchases');

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
            'name' => 'product_id',
            'label' => 'Product',
            'type' => 'select',
            'entity' => 'product',
            'attribute' => 'title',
            'model' => 'App\Models\Product',
        ]);

        CRUD::addColumn([
            'name' => 'supplier_id',
            'label' => 'Supplier',
            'type' => 'select',
            'entity' => 'supplier',
            'attribute' => 'name',
            'model' => 'App\Models\Supplier',
        ]);

        CRUD::addColumn([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'text',
            'limit' => 80,
        ]);

        CRUD::addColumn([
            'name' => 'quantity',
            'label' => 'Quantity',
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'area',
            'label' => 'Area (m²)',
            'type' => 'number',
            'decimals' => 3,
        ]);

        CRUD::addColumn([
            'name' => 'file',
            'label' => 'File',
            'type' => 'upload',
            'disk' => 'public',
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'label' => 'Created At',
            'type' => 'datetime',
        ]);

        CRUD::addFilter([
            'name' => 'product_id',
            'type' => 'select2',
            'label' => 'Product',
        ], function () {
            return \App\Models\Product::all()->pluck('title', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'product_id', $value);
        });

        CRUD::addFilter([
            'name' => 'supplier_id',
            'type' => 'select2',
            'label' => 'Supplier',
        ], function () {
            return \App\Models\Supplier::all()->pluck('name', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'supplier_id', $value);
        });
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(PurchaseRequest::class);

        CRUD::addField([
            'name' => 'product_id',
            'label' => 'Product',
            'type' => 'select',
            'entity' => 'product',
            'model' => 'App\Models\Product',
            'attribute' => 'title',
            'options' => (function ($query) {
                return $query->orderBy('title', 'ASC')->get();
            }),
            'attributes' => [
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'supplier_id',
            'label' => 'Supplier',
            'type' => 'select',
            'entity' => 'supplier',
            'model' => 'App\Models\Supplier',
            'attribute' => 'name',
            'options' => (function ($query) {
                return $query->orderBy('name', 'ASC')->get();
            }),
            'attributes' => [
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'textarea',
        ]);

        CRUD::addField([
            'name' => 'quantity',
            'label' => 'Quantity',
            'type' => 'number',
            'default' => 0,
            'attributes' => [
                'step' => '1',
                'min' => '0',
            ],
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'area',
            'label' => 'Area (m²)',
            'type' => 'number',
            'default' => 0,
            'attributes' => [
                'step' => '0.001',
                'min' => '0',
            ],
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'file',
            'label' => 'File',
            'type' => 'upload',
            'upload' => true,
            'disk' => 'public',
            'attributes' => [
                'accept' => '.pdf,.png,.jpeg,.jpg',
            ],
            'hint' => 'Allowed types: PDF, PNG, JPEG, JPG',
        ]);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
