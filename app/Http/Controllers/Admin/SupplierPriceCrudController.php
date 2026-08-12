<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SupplierPriceRequest;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPrice;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class SupplierPriceCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class SupplierPriceCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(SupplierPrice::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/supplier-price');
        CRUD::setEntityNameStrings(__('supplier_price.entity'), __('supplier_price.entity_plural'));

        $this->crud->enableExportButtons();
    }

    protected function setupListOperation(): void
    {
        // Eager load both relationships to avoid N+1 queries.
        $this->crud->query->with(['product', 'supplier']);

        $this->crud->orderBy('id', 'desc');

        CRUD::addColumn([
            'name' => 'id',
            'label' => __('supplier_price.id'),
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'product_id',
            'label' => __('supplier_price.product'),
            'type' => 'select',
            'entity' => 'product',
            'attribute' => 'title',
        ]);

        CRUD::addColumn([
            'name' => 'supplier_id',
            'label' => __('supplier_price.supplier'),
            'type' => 'select',
            'entity' => 'supplier',
            'attribute' => 'name',
        ]);

        CRUD::addColumn([
            'name' => 'price_usd',
            'label' => __('supplier_price.price_usd'),
            'type' => 'number',
            'decimals' => 2,
            'prefix' => '$',
        ]);

        // The product's own selling price, for quick comparison against what
        // this supplier charges us.
        CRUD::addColumn([
            'name' => 'sale_price',
            'label' => __('supplier_price.sale_price'),
            'type' => 'number',
            'decimals' => 2,
            'prefix' => '$',
            'value' => function ($entry) {
                return $entry->product ? $entry->product->price : null;
            },
        ]);

        CRUD::addColumn([
            'name' => 'updated_at',
            'label' => __('supplier_price.last_updated'),
            'type' => 'datetime',
        ]);

        CRUD::addFilter([
            'name' => 'supplier_id',
            'type' => 'select2',
            'label' => __('supplier_price.supplier'),
        ],
        function () {
            return Supplier::orderBy('name')->pluck('name', 'id')->toArray();
        },
        function ($value) {
            CRUD::addClause('where', 'supplier_id', $value);
        });

        CRUD::addFilter([
            'name' => 'product_id',
            'type' => 'select2',
            'label' => __('supplier_price.product'),
        ],
        function () {
            return Product::orderBy('title')->pluck('title', 'id')->toArray();
        },
        function ($value) {
            CRUD::addClause('where', 'product_id', $value);
        });
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(SupplierPriceRequest::class);

        CRUD::addField([
            'name' => 'product_id',
            'label' => __('supplier_price.product'),
            'type' => 'select2',
            'entity' => 'product',
            'attribute' => 'title',
            'model' => Product::class,
            'allows_null' => false,
            'options' => (function ($query) {
                return $query->orderBy('title')->get();
            }),
            'attributes' => [
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'supplier_id',
            'label' => __('supplier_price.supplier'),
            'type' => 'select2',
            'entity' => 'supplier',
            'attribute' => 'name',
            'model' => Supplier::class,
            'allows_null' => false,
            'options' => (function ($query) {
                return $query->orderBy('name')->get();
            }),
            'attributes' => [
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'price_usd',
            'label' => __('supplier_price.price_usd_field'),
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
                'required' => true,
            ],
            'prefix' => '$',
            'hint' => __('supplier_price.hints.price_usd'),
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
