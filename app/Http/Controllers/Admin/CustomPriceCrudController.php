<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CustomPriceRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;

/**
 * Class CustomPriceCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class CustomPriceCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\CustomPrice::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/custom-price');
        CRUD::setEntityNameStrings('custom price', 'custom prices');
        
        // Enable export buttons
        $this->crud->enableExportButtons();
        
        // Add JavaScript for auto-filling price
        Widget::add()->type('script')->content('assets/js/custom-prices.js');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::addColumn([
            'name' => 'id',
            'label' => 'ID',
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'client_id',
            'label' => 'Client',
            'type' => 'select',
            'entity' => 'client',
            'attribute' => 'name_with_id',
        ]);

        CRUD::addColumn([
            'name' => 'product_id',
            'label' => 'Product',
            'type' => 'select',
            'entity' => 'product',
            'attribute' => 'title',
        ]);

        CRUD::addColumn([
            'name' => 'price_usd',
            'label' => 'Price (USD)',
            'type' => 'number',
            'decimals' => 2,
            'prefix' => '$',
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'label' => 'Created At',
            'type' => 'datetime',
        ]);

        // Add filters
        CRUD::addFilter([
            'name' => 'client_id',
            'type' => 'select2',
            'label' => 'Client',
        ],
        function () {
            return \App\Models\Client::all()->pluck('name_with_id', 'id')->toArray();
        },
        function ($value) {
            CRUD::addClause('where', 'client_id', $value);
        });

        CRUD::addFilter([
            'name' => 'product_id',
            'type' => 'select2',
            'label' => 'Product',
        ],
        function () {
            return \App\Models\Product::all()->pluck('title', 'id')->toArray();
        },
        function ($value) {
            CRUD::addClause('where', 'product_id', $value);
        });
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(CustomPriceRequest::class);

        CRUD::addField([
            'name' => 'client_id',
            'label' => 'Client',
            'type' => 'select2',
            'entity' => 'client',
            'attribute' => 'name_with_id',
            'model' => \App\Models\Client::class,
            'allows_null' => false,
            'attributes' => [
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'product_id',
            'label' => 'Product',
            'type' => 'select2',
            'entity' => 'product',
            'attribute' => 'title',
            'model' => \App\Models\Product::class,
            'allows_null' => false,
            'attributes' => [
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'price_usd',
            'label' => 'Price (USD)',
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
                'required' => true,
            ],
            'prefix' => '$',
        ]);
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
