<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class PaymentCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PaymentCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Payment::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/payment');
        CRUD::setEntityNameStrings('payment', 'payments');
        
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
            'name' => 'client_id',
            'label' => 'Client',
            'type' => 'select',
            'entity' => 'client',
            'attribute' => 'name',
        ]);

        CRUD::addColumn([
            'name' => 'amount_gel',
            'label' => 'Amount GEL',
            'type' => 'number',
            'decimals' => 2,
            'suffix' => ' ₾',
        ]);

        CRUD::addColumn([
            'name' => 'currency_rate',
            'label' => 'Currency Rate',
            'type' => 'number',
            'decimals' => 4,
        ]);

        CRUD::addColumn([
            'name' => 'amount_usd',
            'label' => 'Amount USD',
            'type' => 'number',
            'decimals' => 2,
            'prefix' => '$ ',
        ]);

        CRUD::addColumn([
            'name' => 'method',
            'label' => 'Method',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'status',
            'label' => 'Status',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'payment_date',
            'label' => 'Payment Date',
            'type' => 'datetime',
        ]);

        // Add Filters
        CRUD::addFilter([
            'name' => 'client_id',
            'type' => 'select2',
            'label' => 'Client'
        ], function() {
            return \App\Models\Client::all()->pluck('name', 'id')->toArray();
        }, function($value) {
            $this->crud->addClause('where', 'client_id', $value);
        });

        CRUD::addFilter([
            'name' => 'method',
            'type' => 'select2',
            'label' => 'Payment Method'
        ], function() {
            return [
                'Cash' => 'Cash',
                'Transfer' => 'Transfer',
            ];
        }, function($value) {
            $this->crud->addClause('where', 'method', $value);
        });

        CRUD::addFilter([
            'name' => 'status',
            'type' => 'select2',
            'label' => 'Status'
        ], function() {
            return [
                'Paid' => 'Paid',
                'Pending' => 'Pending',
            ];
        }, function($value) {
            $this->crud->addClause('where', 'status', $value);
        });

        CRUD::addFilter([
            'type' => 'range',
            'name' => 'amount_gel',
            'label' => 'Amount GEL',
            'label_from' => 'Min amount',
            'label_to' => 'Max amount'
        ],
        false,
        function($value) {
            $range = json_decode($value);
            if ($range->from) {
                $this->crud->addClause('where', 'amount_gel', '>=', (float) $range->from);
            }
            if ($range->to) {
                $this->crud->addClause('where', 'amount_gel', '<=', (float) $range->to);
            }
        });

        CRUD::addFilter([
            'type' => 'range',
            'name' => 'amount_usd',
            'label' => 'Amount USD',
            'label_from' => 'Min amount',
            'label_to' => 'Max amount'
        ],
        false,
        function($value) {
            $range = json_decode($value);
            if ($range->from) {
                $this->crud->addClause('where', 'amount_usd', '>=', (float) $range->from);
            }
            if ($range->to) {
                $this->crud->addClause('where', 'amount_usd', '<=', (float) $range->to);
            }
        });

        CRUD::addFilter([
            'name' => 'payment_date',
            'type' => 'date_range',
            'label' => 'Payment Date Range'
        ],
        false,
        function($value) {
            $dates = json_decode($value);
            if ($dates->from) {
                $this->crud->addClause('where', 'payment_date', '>=', $dates->from);
            }
            if ($dates->to) {
                $this->crud->addClause('where', 'payment_date', '<=', $dates->to . ' 23:59:59');
            }
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
        CRUD::addField([
            'name' => 'client_id',
            'label' => 'Client',
            'type' => 'select2',
            'entity' => 'client',
            'attribute' => 'name',
            'model' => \App\Models\Client::class,
            'allows_null' => true,
            'hint' => 'Select the client for this payment',
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ]
        ]);

        CRUD::addField([
            'name' => 'method',
            'label' => 'Payment Method',
            'type' => 'select_from_array',
            'options' => [
                'Cash' => 'Cash',
                'Transfer' => 'Transfer',
            ],
            'allows_null' => false,
            'default' => 'Cash',
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ]
        ]);

        CRUD::addField([
            'name' => 'currency_rate',
            'label' => 'Currency Rate',
            'type' => 'number',
            'attributes' => [
                'step' => '0.0001',
                'min' => '0',
                'required' => true,
            ],
            'hint' => 'Exchange rate for GEL to USD',
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ]
        ]);

        CRUD::addField([
            'name' => 'amount_usd',
            'label' => 'Amount USD',
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
                'required' => true,
            ],
            'prefix' => '$',
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ]
        ]);

        CRUD::addField([
            'name' => 'amount_gel',
            'label' => 'Amount GEL',
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
                'required' => true,
            ],
            'suffix' => '₾',
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ]
        ]);

        

        CRUD::addField([
            'name' => 'file',
            'label' => 'Payment File',
            'type' => 'upload',
            'upload' => true,
            'disk' => 'public',
            'hint' => 'Upload payment related document (invoice, receipt, etc.)',
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ]
        ]);

        CRUD::addField([
            'name' => 'status',
            'label' => 'Status',
            'type' => 'select_from_array',
            'options' => [
                'Paid' => 'Paid',
                'Pending' => 'Pending',
            ],
            'allows_null' => false,
            'default' => 'Pending',
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ]
        ]);


        CRUD::addField([
            'name' => 'payment_date',
            'label' => 'Payment Date',
            'type' => 'datetime_picker',
            'datetime_picker_options' => [
                'format' => 'DD/MM/YYYY HH:mm',
                'language' => 'en'
            ],
            'allows_null' => false,
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ]
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

