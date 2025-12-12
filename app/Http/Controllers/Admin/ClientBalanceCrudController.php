<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;
use App\Models\Client;

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
        // Add summary widget that considers filters
        $this->addClientBalanceStatsWidget();
        
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
                    return $entry->client_type ? 'badge text-bg-warning' : 'badge text-bg-primary';
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

    /**
     * Add widgets for client balance statistics (count, payments, orders, balance).
     * This widget considers active filters.
     * 
     * @return void
     */
    protected function addClientBalanceStatsWidget()
    {
        // Get the model and start building query
        $query = Client::query();
        
        // Apply filters from request (mimic what CRUD filters do)
        if (request()->has('client_type') && request()->get('client_type') !== '') {
            $query->where('client_type', request()->get('client_type'));
        }
        
        if (request()->has('name') && request()->get('name')) {
            $query->where('name', 'LIKE', '%' . request()->get('name') . '%');
        }
        
        if (request()->has('email') && request()->get('email')) {
            $query->where('email', 'LIKE', '%' . request()->get('email') . '%');
        }
        
        if (request()->has('phone_number') && request()->get('phone_number')) {
            $query->where('phone_number', 'LIKE', '%' . request()->get('phone_number') . '%');
        }
        
        // Get all filtered clients with relationships
        $clients = $query->with(['payments', 'orders.pieces', 'orders.products', 'orders.services'])->get();
        
        // Calculate statistics
        $clientsCount = $clients->count();
        
        // Total payments (only paid payments) - use loaded relationships
        $totalPayments = $clients->sum(function($client) {
            return $client->payments->where('status', 'Paid')->sum('amount_gel') ?? 0;
        });
        
        // Total orders (excluding draft orders) - use loaded relationships
        $totalOrders = $clients->sum(function($client) {
            return $client->orders->where('status', '!=', 'draft')->sum(function($order) {
                return $order->calculateTotalPrice();
            });
        });
        
        // Total balance
        $totalBalance = $totalPayments - $totalOrders;
        
        // Add widget - pass data to view
        Widget::add([
            'type' => 'view',
            'view' => 'vendor.backpack.crud.widgets.client_balance_stats',
            'wrapper' => ['class' => 'col-12', 'id' => 'client-balance-stats-widget'],
            'clientsCount' => $clientsCount,
            'totalPayments' => $totalPayments,
            'totalOrders' => $totalOrders,
            'totalBalance' => $totalBalance,
        ])->to('before_content');
    }

    /**
     * Get client balance statistics via AJAX (for dynamic widget updates).
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBalanceStats()
    {
        // Get the model and start building query
        $query = Client::query();
        
        // Apply filters from request
        if (request()->has('client_type') && request()->get('client_type') !== '') {
            $query->where('client_type', request()->get('client_type'));
        }
        
        if (request()->has('name') && request()->get('name')) {
            $query->where('name', 'LIKE', '%' . request()->get('name') . '%');
        }
        
        if (request()->has('email') && request()->get('email')) {
            $query->where('email', 'LIKE', '%' . request()->get('email') . '%');
        }
        
        if (request()->has('phone_number') && request()->get('phone_number')) {
            $query->where('phone_number', 'LIKE', '%' . request()->get('phone_number') . '%');
        }
        
        // Get all filtered clients with relationships
        $clients = $query->with(['payments', 'orders.pieces', 'orders.products', 'orders.services'])->get();
        
        // Calculate statistics
        $clientsCount = $clients->count();
        
        // Total payments (only paid payments) - use loaded relationships
        $totalPayments = $clients->sum(function($client) {
            return $client->payments->where('status', 'Paid')->sum('amount_gel') ?? 0;
        });
        
        // Total orders (excluding draft orders) - use loaded relationships
        $totalOrders = $clients->sum(function($client) {
            return $client->orders->where('status', '!=', 'draft')->sum(function($order) {
                return $order->calculateTotalPrice();
            });
        });
        
        // Total balance
        $totalBalance = $totalPayments - $totalOrders;
        
        return response()->json([
            'clientsCount' => $clientsCount,
            'totalPayments' => $totalPayments,
            'totalOrders' => $totalOrders,
            'totalBalance' => $totalBalance,
        ]);
    }
}

