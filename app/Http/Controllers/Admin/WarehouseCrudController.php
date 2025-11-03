<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\WarehouseRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class WarehouseCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class WarehouseCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Warehouse::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/warehouse');
        CRUD::setEntityNameStrings('warehouse', 'warehouses');
        
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
        // Set custom button label for list page
        CRUD::setEntityNameStrings('warehouse item', 'warehouses');
        
        $this->crud->orderBy('id', 'desc');
        
        $this->crud->addColumn([
            'name' => 'id',
            'label' => 'ID',
            'type' => 'number',
        ]);

        $this->crud->addColumn([
            'name' => 'product_id',
            'label' => 'Product',
            'type' => 'select',
            'entity' => 'product',
            'attribute' => 'title',
            'model' => 'App\Models\Product',
        ]);

        $this->crud->addColumn([
            'name' => 'unit_of_measure',
            'label' => 'Unit of Measure',
            'type' => 'text',
        ]);

        $this->crud->addColumn([
            'name' => 'value',
            'label' => 'Value',
            'type' => 'number',
            'decimals' => 3,
        ]);

        $this->crud->addColumn([
            'name' => 'created_at',
            'label' => 'Created At',
            'type' => 'datetime',
        ]);

        // Add Filters
        $this->crud->addFilter([
            'name' => 'product_id',
            'type' => 'select2',
            'label' => 'Product'
        ], function() {
            return \App\Models\Product::all()->pluck('title', 'id')->toArray();
        }, function($value) {
            $this->crud->addClause('where', 'product_id', $value);
        });

        $this->crud->addFilter([
            'name' => 'unit_of_measure',
            'type' => 'select2',
            'label' => 'Unit of Measure'
        ], function() {
            return [
                'pieces' => 'ცალი',
                'cubic_meters' => 'კვ.მ',
            ];
        }, function($value) {
            $this->crud->addClause('where', 'unit_of_measure', $value);
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
        CRUD::setValidation(WarehouseRequest::class);
        
        // Set custom button label
        CRUD::setEntityNameStrings('warehouse item', 'warehouses');

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
            'name' => 'unit_of_measure',
            'label' => 'Unit of Measure',
            'type' => 'select_from_array',
            'options' => [
                'ცალი' => 'ცალი',
                'კვ.მ' => 'კვ.მ',
            ],
            'allows_null' => false,
            'default' => 'ცალი',
            'attributes' => [
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'value',
            'label' => 'Value',
            'type' => 'number',
            'attributes' => [
                'step' => '1',
                'min' => '0',
                'required' => true,
            ],
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
