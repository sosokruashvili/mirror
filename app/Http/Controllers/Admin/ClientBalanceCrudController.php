<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;
use App\Models\Client;
use App\Services\ClientBalanceService;

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

        // Manual "recalculate now" button (re-runs today's snapshot on demand).
        $this->crud->addButtonFromView('top', 'recalculate_balances', 'recalculate_balances', 'beginning');

        // Eager load the latest stored balance snapshot to avoid N+1 queries.
        $this->crud->addClause('with', 'latestBalance');

        CRUD::addColumn([
            'name' => 'id',
            'label' => 'ID',
            'type' => 'number',
            'searchLogic' => false,
        ]);

        CRUD::addColumn([
            'name' => 'name_with_id',
            'label' => 'Client',
            'type' => 'text',
            'limit' => 9999,
            // Global search should only match the real clients.name column
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('name', 'like', '%' . $searchTerm . '%');
            },
        ]);

        CRUD::addColumn([
            'name' => 'client_type',
            'label' => 'Type',
            'type' => 'boolean',
            'options' => [0 => 'Individual', 1 => 'Legal'],
            'searchLogic' => false,
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
            'searchLogic' => false,
        ]);

        CRUD::addColumn([
            'name' => 'email',
            'label' => 'Email',
            'type' => 'email',
            'searchLogic' => false,
        ]);

        // Payments total (from the latest daily snapshot)
        CRUD::addColumn([
            'name' => 'payments_total',
            'label' => 'Payments Total',
            'type' => 'number',
            'decimals' => 0,
            'suffix' => ' ₾',
            'searchLogic' => false,
            'value' => function ($entry) {
                return $this->resolveRowComponents($entry)['payments_total'];
            },
        ]);

        // Orders total (from the latest daily snapshot)
        CRUD::addColumn([
            'name' => 'orders_total',
            'label' => 'Orders Total',
            'type' => 'number',
            'decimals' => 0,
            'suffix' => ' ₾',
            'searchLogic' => false,
            'value' => function ($entry) {
                return $this->resolveRowComponents($entry)['orders_total'];
            },
        ]);

        // Balance (from the latest daily snapshot)
        CRUD::addColumn([
            'name' => 'balance',
            'label' => 'Balance',
            'type' => 'number',
            'decimals' => 0,
            'suffix' => ' ₾',
            'searchLogic' => false,
            'value' => function ($entry) {
                return $this->resolveRowComponents($entry)['balance'];
            },
            'wrapper' => [
                'element' => 'span',
                'class' => function ($crud, $column, $entry, $related_key) {
                    $balance = $this->resolveRowComponents($entry)['balance'];
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
     * Resolve the balance components (payments_total, orders_total, balance) for a
     * client row. Reads the latest stored daily snapshot when available and falls
     * back to a live calculation when the client has no snapshot yet (e.g. before
     * the first scheduled run). Memoized per client for the duration of the request.
     *
     * @return array{payments_total: float, orders_total: float, balance: float}
     */
    protected function resolveRowComponents($client): array
    {
        if (isset($this->rowComponentsCache[$client->id])) {
            return $this->rowComponentsCache[$client->id];
        }

        if ($client->latestBalance) {
            $components = [
                'payments_total' => (float) $client->latestBalance->payments_total,
                'orders_total' => (float) $client->latestBalance->orders_total,
                'balance' => (float) $client->latestBalance->balance,
            ];
        } else {
            $components = app(ClientBalanceService::class)->calculateComponentsForClient($client);
        }

        return $this->rowComponentsCache[$client->id] = $components;
    }

    /**
     * In-request memoization cache for resolveRowComponents().
     *
     * @var array<int, array{payments_total: float, orders_total: float, balance: float}>
     */
    protected $rowComponentsCache = [];

    /**
     * Build the filtered set of clients (with the latest snapshot eager loaded)
     * used by the stats widget and its AJAX counterpart.
     */
    protected function getFilteredClientsForStats()
    {
        $query = Client::query()->with('latestBalance');

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

        return $query->get();
    }

    /**
     * Aggregate stored-snapshot stats across a collection of clients.
     *
     * @return array{clientsCount:int, totalPayments:float, totalOrders:float, totalBalance:float}
     */
    protected function aggregateStats($clients): array
    {
        $totalPayments = 0.0;
        $totalOrders = 0.0;

        foreach ($clients as $client) {
            $components = $this->resolveRowComponents($client);
            $totalPayments += $components['payments_total'];
            $totalOrders += $components['orders_total'];
        }

        return [
            'clientsCount' => $clients->count(),
            'totalPayments' => $totalPayments,
            'totalOrders' => $totalOrders,
            'totalBalance' => $totalPayments - $totalOrders,
        ];
    }

    /**
     * Add widgets for client balance statistics (count, payments, orders, balance).
     * This widget considers active filters and reads from stored daily snapshots.
     *
     * @return void
     */
    protected function addClientBalanceStatsWidget()
    {
        $stats = $this->aggregateStats($this->getFilteredClientsForStats());

        $clientsCount = $stats['clientsCount'];
        $totalPayments = $stats['totalPayments'];
        $totalOrders = $stats['totalOrders'];
        $totalBalance = $stats['totalBalance'];

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
        $stats = $this->aggregateStats($this->getFilteredClientsForStats());

        return response()->json($stats);
    }

    /**
     * Manually re-run today's balance snapshot for all clients, then return the
     * refreshed (filter-aware) stats so the widget can update without a reload.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function recalculate(ClientBalanceService $service)
    {
        $count = $service->snapshotDailyBalances(now());

        $stats = $this->aggregateStats($this->getFilteredClientsForStats());

        return response()->json(array_merge([
            'message' => "Recalculated balances for {$count} client(s).",
        ], $stats));
    }
}

