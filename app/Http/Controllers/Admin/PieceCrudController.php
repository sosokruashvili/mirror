<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class PieceCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PieceCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
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
        CRUD::setModel(\App\Models\Piece::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/piece');
        CRUD::setEntityNameStrings('piece', 'pieces');
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
            'name' => 'order_id',
            'label' => 'Order',
            'type' => 'relationship',
            'entity' => 'order',
            'attribute' => 'id', // Show the Order ID
            'model' => \App\Models\Order::class,
        ]);

        CRUD::addColumn([
            'name' => 'order.product_type',
            'label' => 'Order Product Type',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'status',
            'label' => 'Status',
            'type' => 'custom_html',
            'value' => function ($entry) {
                return status_badge($entry->status);
            }
        ]);

        CRUD::addColumn([
            'name' => 'width',
            'label' => 'Width',
            'type' => 'number',
            'decimals' => 0,
            'thousands_sep' => '',
        ]);

        CRUD::addColumn([
            'name' => 'height',
            'label' => 'Height',
            'type' => 'number',
            'decimals' => 0,
            'thousands_sep' => '',
        ]);

        

        CRUD::addColumn([
            'name' => 'quantity',
            'label' => 'Quantity',
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'label' => 'Created At',
            'type' => 'datetime',
        ]);

        // Add filter for order_id if present in URL
        if (request()->has('order_id')) {
            $this->crud->addClause('where', 'order_id', request()->get('order_id'));
        }

        // Add filter for order_id
        $this->crud->addFilter([
            'name' => 'order_id',
            'type' => 'select2',
            'label' => 'Order'
        ], function() {
            return \App\Models\Order::all()->pluck('id', 'id')->toArray();
        }, function($value) {
            $this->crud->addClause('where', 'order_id', $value);
        });
    }


    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        CRUD::addField([
            'name' => 'quantity',
            'label' => 'Quantity',
            'type' => 'number',
            'attributes' => [
                'min' => '1',
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'order_id',
            'label' => 'Order',
            'type' => 'select',
            'entity' => 'order',
            'attribute' => 'name',
            'model' => \App\Models\Order::class,
        ]);

        CRUD::addField([
            'name' => 'product_id',
            'label' => 'Product',
            'type' => 'select',
            'entity' => 'product',
            'attribute' => 'title',
            'model' => \App\Models\Product::class,
        ]);

        CRUD::addField([
            'name' => 'width',
            'label' => 'Width',
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'height',
            'label' => 'Height',
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'status',
            'label' => 'Status',
            'type' => 'select_from_array',
            'options' => [
                'pending' => 'Pending',
                'in_progress' => 'In Progress',
                'completed' => 'Completed',
                'ready' => 'Ready',
                'cancelled' => 'Cancelled',
            ],
        ]);
    }
}
