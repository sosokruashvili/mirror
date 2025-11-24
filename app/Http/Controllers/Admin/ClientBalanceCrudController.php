<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class ClientBalanceCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ClientBalanceCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Client::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/client-balance');
        CRUD::setEntityNameStrings('client balance', 'client balances');
        
        // Disable create, update, delete operations (read-only)
        $this->crud->denyAccess(['create', 'update', 'delete', 'show']);
        
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
            'name' => 'id',
            'label' => 'ID',
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'name_with_id',
            'label' => 'Client',
            'type' => 'text',
            'limit' => 9999,
        ]);

        CRUD::addColumn([
            'name' => 'client_type',
            'label' => 'Type',
            'type' => 'boolean',
            'options' => [0 => 'Individual', 1 => 'Legal'],
            'wrapper' => [
                'element' => 'span',
                'class' => function ($crud, $column, $entry, $related_key) {
                    return $entry->client_type ? 'badge badge-success' : 'badge badge-info';
                }
            ]
        ]);

        CRUD::addColumn([
            'name' => 'phone_number',
            'label' => 'Phone',
            'type' => 'phone',
        ]);

        CRUD::addColumn([
            'name' => 'email',
            'label' => 'Email',
            'type' => 'email',
        ]);

        // Payments total (only paid payments)
        CRUD::addColumn([
            'name' => 'payments_total',
            'label' => 'Payments Total',
            'type' => 'number',
            'decimals' => 2,
            'suffix' => ' ₾',
            'value' => function ($entry) {
                return $entry->payments()->where('status', 'Paid')->sum('amount_gel') ?? 0;
            },
        ]);

        // Orders total (excluding draft orders)
        CRUD::addColumn([
            'name' => 'orders_total',
            'label' => 'Orders Total',
            'type' => 'number',
            'decimals' => 2,
            'suffix' => ' ₾',
            'value' => function ($entry) {
                return $entry->orders()->where('status', '!=', 'draft')->get()->sum(function($order) {
                    return $order->calculateTotalPrice();
                });
            },
        ]);

        // Balance
        CRUD::addColumn([
            'name' => 'balance',
            'label' => 'Balance',
            'type' => 'number',
            'decimals' => 2,
            'suffix' => ' ₾',
            'value' => function ($entry) {
                return $entry->calculateBalance();
            },
            'wrapper' => [
                'element' => 'span',
                'class' => function ($crud, $column, $entry, $related_key) {
                    $balance = $entry->calculateBalance();
                    return $balance >= 0 ? 'text-success' : 'text-danger';
                }
            ]
        ]);

        // Add Filters
        CRUD::addFilter([
            'name' => 'client_type',
            'type' => 'select2',
            'label' => 'Client Type'
        ], function() {
            return [
                0 => 'Individual',
                1 => 'Legal',
            ];
        }, function($value) {
            $this->crud->addClause('where', 'client_type', $value);
        });

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'name',
            'label' => 'Name'
        ],
        false,
        function($value) {
            $this->crud->addClause('where', 'name', 'LIKE', "%{$value}%");
        });

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'email',
            'label' => 'Email'
        ],
        false,
        function($value) {
            $this->crud->addClause('where', 'email', 'LIKE', "%{$value}%");
        });

        CRUD::addFilter([
            'type' => 'text',
            'name' => 'phone_number',
            'label' => 'Phone'
        ],
        false,
        function($value) {
            $this->crud->addClause('where', 'phone_number', 'LIKE', "%{$value}%");
        });

        // Default ordering by ID ascending
        $this->crud->orderBy('id', 'asc');
    }
}

