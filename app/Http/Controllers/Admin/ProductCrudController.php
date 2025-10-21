<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ProductRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class ProductCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ProductCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Product::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/product');
        CRUD::setEntityNameStrings('product', 'products');
        
        // Enable export buttons
        $this->crud->enableExportButtons();
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
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'product_type',
            'label' => 'Product Type',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'price',
            'label' => 'Price (USD)',
            'type' => 'number',
            'decimals' => 2,
            'prefix' => '$',
        ]);

        CRUD::addColumn([
            'name' => 'price_w',
            'label' => 'Wholesale Price (USD)',
            'type' => 'number',
            'decimals' => 2,
            'prefix' => '$',
        ]);

        // Add Filters
        CRUD::addFilter([
            'name' => 'product_type',
            'type' => 'select2',
            'label' => 'Product Type'
        ], function() {
            return [
                'glass' => 'Glass',
                'film' => 'Film',
            ];
        }, function($value) {
            $this->crud->addClause('where', 'product_type', $value);
        });

        CRUD::addFilter([
            'type' => 'range',
            'name' => 'price',
            'label' => 'Retail Price',
            'label_from' => 'Min price',
            'label_to' => 'Max price'
        ],
        false,
        function($value) {
            $range = json_decode($value);
            if ($range->from) {
                $this->crud->addClause('where', 'price', '>=', (float) $range->from);
            }
            if ($range->to) {
                $this->crud->addClause('where', 'price', '<=', (float) $range->to);
            }
        });

        CRUD::addFilter([
            'type' => 'range',
            'name' => 'price_w',
            'label' => 'Wholesale Price',
            'label_from' => 'Min price',
            'label_to' => 'Max price'
        ],
        false,
        function($value) {
            $range = json_decode($value);
            if ($range->from) {
                $this->crud->addClause('where', 'price_w', '>=', (float) $range->from);
            }
            if ($range->to) {
                $this->crud->addClause('where', 'price_w', '<=', (float) $range->to);
            }
        });

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'title',
            'label' => 'Title'
        ],
        false,
        function($value) {
            $this->crud->addClause('where', 'title', 'LIKE', "%{$value}%");
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
        CRUD::setValidation(ProductRequest::class);

        CRUD::addField([
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
            'attributes' => [
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'product_type',
            'label' => 'Product Type',
            'type' => 'select_from_array',
            'options' => [
                'glass' => 'Glass',
                'film' => 'Film',
            ],
            'allows_null' => false,
            'default' => 'glass',
        ]);

        CRUD::addField([
            'name' => 'price',
            'label' => 'Retail Price (USD)',
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
                'required' => true,
            ],
            'prefix' => '$',
        ]);

        CRUD::addField([
            'name' => 'price_w',
            'label' => 'Wholesale Price (USD)',
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
            ],
            'prefix' => '$',
            'hint' => 'Optional wholesale price for bulk purchases',
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
