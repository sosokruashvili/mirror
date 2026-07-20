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
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
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

        // Expandable rows: clicking a client row loads their payments and orders inline.
        // The custom list view makes the whole row (not just the +/- icon) the trigger.
        $this->crud->enableDetailsRow();
        $this->crud->setDetailsRowView('vendor.backpack.crud.details_rows.client_balance');
        $this->crud->setListView('vendor.backpack.crud.client_balance.list');

        // Resolve the "as of" date once for the whole request and make the
        // balanceForDate relationship aware of it, so eager loading and the
        // whereHas range filters all read the same historical snapshot.
        $balanceDate = $this->selectedBalanceDate();
        Client::$balanceAsOfDate = $balanceDate;

        // Eager load the stored balance snapshot to avoid N+1 queries. When a
        // date filter is active we load the snapshot as of that date; otherwise
        // the latest snapshot.
        $this->crud->addClause('with', $balanceDate ? 'balanceForDate' : 'latestBalance');

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

        // Starting balance (manually entered opening balance on the client)
        CRUD::addColumn([
            'name' => 'starting_balance',
            'label' => 'Starting Balance',
            'type' => 'number',
            'decimals' => 0,
            'suffix' => ' ₾',
            'searchLogic' => false,
            'value' => function ($entry) {
                return $this->resolveRowComponents($entry)['starting_balance'];
            },
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

        // "Balance as of" date. Shows each client's balance from the snapshot on
        // or before the chosen date instead of the latest one. The actual snapshot
        // selection is wired up above via selectedBalanceDate(); this closure only
        // needs to exist so Backpack renders and preserves the filter.
        CRUD::addFilter([
            'type' => 'date',
            'name' => 'balance_date',
            'label' => 'Balance as of date',
        ], false, function ($value) {
            // No-op: handled globally in setupListOperation via selectedBalanceDate().
        });

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

        // Range filters on the latest snapshot amounts (payments, orders, balance).
        CRUD::addFilter([
            'name' => 'payments_total',
            'type' => 'range',
            'label' => 'Payments Total',
            'label_from' => 'Min',
            'label_to' => 'Max',
        ], false, function ($value) {
            $range = json_decode($value);
            $this->applyLatestBalanceRange($this->crud->query, 'payments_total', $range->from ?? null, $range->to ?? null);
        });

        CRUD::addFilter([
            'name' => 'orders_total',
            'type' => 'range',
            'label' => 'Orders Total',
            'label_from' => 'Min',
            'label_to' => 'Max',
        ], false, function ($value) {
            $range = json_decode($value);
            $this->applyLatestBalanceRange($this->crud->query, 'orders_total', $range->from ?? null, $range->to ?? null);
        });

        CRUD::addFilter([
            'name' => 'balance',
            'type' => 'range',
            'label' => 'Balance',
            'label_from' => 'Min',
            'label_to' => 'Max',
        ], false, function ($value) {
            $range = json_decode($value);
            $this->applyLatestBalanceRange($this->crud->query, 'balance', $range->from ?? null, $range->to ?? null);
        });

        // Default ordering by ID ascending
        $this->crud->orderBy('id', 'asc');
    }

    /**
     * Render the expanded sub-list for one client row: their payments and orders.
     * Called over AJAX by the details-row logic in the list view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function showDetailsRow($id)
    {
        $this->crud->hasAccessOrFail('list');

        $client = Client::query()
            ->with([
                'latestBalance',
                'payments' => function ($query) {
                    $query->orderByDesc('payment_date')->orderByDesc('id');
                },
                'payments.order',
                // calculateTotalPrice() walks services/products/pieces on each order,
                // so eager load them to keep the expanded row to a handful of queries.
                'orders' => function ($query) {
                    $query->orderByDesc('id');
                },
                'orders.services',
                'orders.products',
                'orders.pieces',
            ])
            ->findOrFail($id);

        // Only these count towards the balance; the view greys out the rest.
        $countedPayments = $client->payments->where('status', 'Paid');
        $countedOrders = $client->orders->where('status', '!=', 'draft');

        return view('vendor.backpack.crud.details_rows.client_balance', [
            'crud' => $this->crud,
            'entry' => $client,
            'components' => $this->resolveRowComponents($client),
            'payments' => $client->payments,
            'orders' => $client->orders,
            // persist: false — this is a read-only screen, so price the orders
            // without writing the computed price back to every piece.
            'orderTotals' => $client->orders->mapWithKeys(function ($order) {
                return [$order->id => (float) $order->calculateTotalPrice(false)];
            }),
            'countedPaymentsTotal' => (float) $countedPayments->sum('amount_gel'),
            'countedPaymentsCount' => $countedPayments->count(),
            'countedOrderIds' => $countedOrders->pluck('id')->all(),
        ]);
    }

    /**
     * Constrain a client query to those whose latest balance snapshot has the given
     * column within [$from, $to]. Either bound may be null/empty to leave it open.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    protected function applyLatestBalanceRange($query, string $column, $from, $to)
    {
        $hasFrom = $from !== null && $from !== '';
        $hasTo = $to !== null && $to !== '';

        if (!$hasFrom && !$hasTo) {
            return;
        }

        // Range on whichever snapshot the screen is currently showing: the
        // as-of-date snapshot when a date filter is active, else the latest.
        $relation = $this->selectedBalanceDate() ? 'balanceForDate' : 'latestBalance';

        $query->whereHas($relation, function ($q) use ($column, $from, $to, $hasFrom, $hasTo) {
            if ($hasFrom) {
                $q->where($column, '>=', $from);
            }
            if ($hasTo) {
                $q->where($column, '<=', $to);
            }
        });
    }

    /**
     * The validated "balance as of" date (Y-m-d) from the request, or null when
     * no date filter is active. Memoized for the request.
     *
     * @return string|null
     */
    protected function selectedBalanceDate(): ?string
    {
        if ($this->selectedBalanceDateResolved) {
            return $this->selectedBalanceDateCache;
        }

        $this->selectedBalanceDateResolved = true;

        $value = request('balance_date');

        if (!$value) {
            return $this->selectedBalanceDateCache = null;
        }

        try {
            return $this->selectedBalanceDateCache = \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Exception $e) {
            return $this->selectedBalanceDateCache = null;
        }
    }

    /** @var bool */
    protected $selectedBalanceDateResolved = false;

    /** @var string|null */
    protected $selectedBalanceDateCache = null;

    /**
     * Resolve the balance components (payments_total, orders_total, balance) for a
     * client row. Reads the latest stored daily snapshot when available and falls
     * back to a live calculation when the client has no snapshot yet (e.g. before
     * the first scheduled run). Memoized per client for the duration of the request.
     *
     * @return array{starting_balance: float, payments_total: float, orders_total: float, balance: float}
     */
    protected function resolveRowComponents($client): array
    {
        if (isset($this->rowComponentsCache[$client->id])) {
            return $this->rowComponentsCache[$client->id];
        }

        $date = $this->selectedBalanceDate();
        $snapshot = $date ? $client->balanceForDate : $client->latestBalance;

        if ($snapshot) {
            $components = [
                'starting_balance' => (float) $snapshot->starting_balance,
                'payments_total' => (float) $snapshot->payments_total,
                'orders_total' => (float) $snapshot->orders_total,
                'balance' => (float) $snapshot->balance,
            ];
        } elseif ($date) {
            // A specific date was requested but this client has no snapshot on or
            // before it (e.g. the client didn't exist yet, or predates snapshots).
            // Show zeros rather than the current live balance, which would be
            // misleading on a historical view.
            $components = [
                'starting_balance' => 0.0,
                'payments_total' => 0.0,
                'orders_total' => 0.0,
                'balance' => 0.0,
            ];
        } else {
            $components = app(ClientBalanceService::class)->calculateComponentsForClient($client);
        }

        return $this->rowComponentsCache[$client->id] = $components;
    }

    /**
     * In-request memoization cache for resolveRowComponents().
     *
     * @var array<int, array{starting_balance: float, payments_total: float, orders_total: float, balance: float}>
     */
    protected $rowComponentsCache = [];

    /**
     * Build the filtered set of clients (with the latest snapshot eager loaded)
     * used by the stats widget and its AJAX counterpart.
     */
    protected function getFilteredClientsForStats()
    {
        // Match the table's snapshot selection. This method is also reached by
        // standalone AJAX endpoints (getBalanceStats / recalculate) that don't run
        // setupListOperation, so set the relationship date here too.
        $balanceDate = $this->selectedBalanceDate();
        Client::$balanceAsOfDate = $balanceDate;

        $query = Client::query()->with($balanceDate ? 'balanceForDate' : 'latestBalance');

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

        // Mirror the snapshot range filters so the stats match the filtered table.
        foreach (['payments_total', 'orders_total', 'balance'] as $column) {
            if (request()->filled($column)) {
                $range = json_decode(request()->get($column));
                if ($range) {
                    $this->applyLatestBalanceRange($query, $column, $range->from ?? null, $range->to ?? null);
                }
            }
        }

        return $query->get();
    }

    /**
     * Aggregate stored-snapshot stats across a collection of clients.
     *
     * @return array{clientsCount:int, totalStarting:float, totalPayments:float, totalOrders:float, totalBalance:float}
     */
    protected function aggregateStats($clients): array
    {
        $totalStarting = 0.0;
        $totalPayments = 0.0;
        $totalOrders = 0.0;
        $totalBalance = 0.0;

        foreach ($clients as $client) {
            $components = $this->resolveRowComponents($client);
            $totalStarting += $components['starting_balance'];
            $totalPayments += $components['payments_total'];
            $totalOrders += $components['orders_total'];
            $totalBalance += $components['balance'];
        }

        return [
            'clientsCount' => $clients->count(),
            'totalStarting' => $totalStarting,
            'totalPayments' => $totalPayments,
            'totalOrders' => $totalOrders,
            'totalBalance' => $totalBalance,
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
        $totalStarting = $stats['totalStarting'];
        $totalPayments = $stats['totalPayments'];
        $totalOrders = $stats['totalOrders'];
        $totalBalance = $stats['totalBalance'];

        // Add widget - pass data to view
        Widget::add([
            'type' => 'view',
            'view' => 'vendor.backpack.crud.widgets.client_balance_stats',
            'wrapper' => ['class' => 'col-12', 'id' => 'client-balance-stats-widget'],
            'clientsCount' => $clientsCount,
            'totalStarting' => $totalStarting,
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

