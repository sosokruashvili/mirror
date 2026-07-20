<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;
use App\Models\Currency;
use App\Models\Client;
use App\Models\Payment;

/**
 * Class PaymentCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PaymentCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
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
        
        // Add JavaScript to reload page on filter change (so widgets update)
        Widget::add()->type('script')->content('assets/js/payment-filters-reload.js');
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
        $this->addPaymentStatsWidget();

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
            'name' => 'method',
            'label' => 'Method',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'type',
            'label' => 'Type',
            'type' => 'select_from_array',
            'options' => Payment::types(),
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
        // Apply order_id from URL so links from the order preview work immediately
        if (request()->has('order_id') && request()->get('order_id') !== '') {
            $this->crud->addClause('where', 'order_id', request()->get('order_id'));
        }

        CRUD::addFilter([
            'name' => 'order_id',
            'type' => 'select2',
            'label' => 'Order'
        ], function() {
            return \App\Models\Order::all()->pluck('id', 'id')->toArray();
        }, function($value) {
            $this->crud->addClause('where', 'order_id', $value);
        });

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
                'Terminal' => 'Terminal',
                'PM Transfer' => 'PM Transfer',
            ];
        }, function($value) {
            $this->crud->addClause('where', 'method', $value);
        });

        CRUD::addFilter([
            'name' => 'type',
            'type' => 'select2',
            'label' => 'Type'
        ], function() {
            return Payment::types();
        }, function($value) {
            $this->crud->addClause('where', 'type', $value);
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
            'name' => 'payment_date',
            'type' => 'date_range',
            'label' => 'Payment Date Range'
        ],
        false,
        function($value) {
            $dates = json_decode($value);
            if ($dates->from) {
                // Ensure from date is at 00:00:00 - remove any existing time component
                $fromDate = date('Y-m-d', strtotime($dates->from)) . ' 00:00:00';
                $this->crud->addClause('where', 'payment_date', '>=', $fromDate);
            }
            if ($dates->to) {
                // Ensure to date is at 23:59:59 - remove any existing time component first
                $toDate = date('Y-m-d', strtotime($dates->to)) . ' 23:59:59';
                $this->crud->addClause('where', 'payment_date', '<=', $toDate);
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
        CRUD::setValidation(\App\Http\Requests\PaymentRequest::class);

        // Add JavaScript to display client balance on create/update forms
        Widget::add()->type('script')->content('assets/js/payment-client-balance.js');
        // Show/populate Order when type = შეკვეთა, and auto-fill Amount from order price.
        // Filename is versioned so browsers pick up fixes (Basset cache-busts via composer.lock only).
        Widget::add()->type('script')->content('assets/js/payment-create-order.js');

        CRUD::addField([
            'name' => 'client_id',
            'label' => 'Client',
            'type' => 'select2',
            'entity' => 'client',
            'attribute' => 'name_with_id',
            'model' => \App\Models\Client::class,
            'allows_null' => false,
            'hint' => 'Select the client for this payment',
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ],
            'attributes' => [
                'id' => 'client_id_field',
                'required' => 'required',
            ]
        ]);

        // Add a custom field to display client balance
        CRUD::addField([
            'name' => 'client_balance_display',
            'type' => 'custom_html',
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ],
            'value' => '<div id="client_balance_display" style="display: none;">
                <label class="form-label" style="margin-bottom: 0;">Client Balance</label>
                <div class="form-control" style=" padding: 0.375rem 0.75rem; min-height: 38px; display: flex; align-items: center;">
                    <span id="client_balance_value" style="font-weight: 600; font-size: 1rem;">-</span>
                </div>
            </div>',
        ]);

        CRUD::addField([
            'name' => 'method',
            'label' => 'Payment Method',
            'type' => 'select_from_array',
            'options' => [
                'Cash' => 'Cash',
                'Transfer' => 'Transfer',
                'Terminal' => 'Terminal',
                'PM Transfer' => 'PM Transfer',
            ],
            'allows_null' => false,
            'default' => 'Cash',
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ]
        ]);

        CRUD::addField([
            'name' => 'type',
            'label' => 'Payment Type',
            'type' => 'select_from_array',
            'options' => Payment::types(),
            'allows_null' => false,
            'default' => Payment::TYPE_ORDER,
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ],
            'attributes' => [
                'id' => 'payment_type_field',
            ]
        ]);

        // Order selector — only relevant (and only shown by JS) when Payment Type is
        // "Order" (შეკვეთა). Options are populated dynamically from the selected
        // client's orders via assets/js/payment-create-order.js. On edit, the currently
        // linked order is passed through so JS can pre-select it once options load.
        CRUD::addField([
            'name' => 'order_id',
            'label' => 'Order',
            'type' => 'select_from_array',
            'options' => $this->orderFieldOptions(),
            'allows_null' => true,
            'hint' => 'Select which of the client\'s orders this payment is for',
            'wrapper' => [
                'class' => 'form-group col-md-6',
                'id' => 'order_id_wrapper',
                'style' => 'display: none;',
            ],
            'attributes' => [
                'id' => 'order_id_field',
                'data-selected-order' => $this->currentOrderId(),
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
            'default' => Currency::exchangeRate(),
            'hint' => 'Exchange rate for GEL to USD',
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
                'id' => 'amount_gel_field',
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
            'default' => 'Paid',
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
            'default' => now(),
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

    /**
     * The order_id currently linked to the payment being edited (null on create).
     * Passed to the JS so it can pre-select the saved order once options load.
     *
     * @return int|null
     */
    protected function currentOrderId()
    {
        if ($this->crud->getCurrentOperation() === 'update') {
            return optional($this->crud->getCurrentEntry())->order_id;
        }

        return null;
    }

    /**
     * Initial options for the Order select field.
     *
     * On create there is no client yet, so options are empty and get populated
     * client-side. On update we seed the current client's orders so the saved
     * value renders correctly even before the JS runs.
     *
     * @return array<int|string, string>
     */
    protected function orderFieldOptions()
    {
        if ($this->crud->getCurrentOperation() !== 'update') {
            return [];
        }

        $entry = $this->crud->getCurrentEntry();
        if (!$entry || !$entry->client_id) {
            return [];
        }

        return \App\Models\Order::where('client_id', $entry->client_id)
            ->with(['pieces', 'products', 'services'])
            ->get()
            ->pluck('order_display', 'id')
            ->toArray();
    }

    /**
     * Apply filters to payment query based on request parameters.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyPaymentFilters($query)
    {
        if (request()->has('order_id') && request()->get('order_id') !== '') {
            $query->where('order_id', request()->get('order_id'));
        }

        if (request()->has('client_id') && request()->get('client_id') !== '') {
            $query->where('client_id', request()->get('client_id'));
        }
        
        if (request()->has('method') && request()->get('method') !== '') {
            $query->where('method', request()->get('method'));
        }

        if (request()->has('type') && request()->get('type') !== '') {
            $query->where('type', request()->get('type'));
        }

        if (request()->has('status') && request()->get('status') !== '') {
            $query->where('status', request()->get('status'));
        }
        
        if (request()->has('amount_gel') && request()->get('amount_gel')) {
            $range = json_decode(request()->get('amount_gel'));
            if ($range && isset($range->from)) {
                $query->where('amount_gel', '>=', (float) $range->from);
            }
            if ($range && isset($range->to)) {
                $query->where('amount_gel', '<=', (float) $range->to);
            }
        }
        
        if (request()->has('amount_usd') && request()->get('amount_usd')) {
            $range = json_decode(request()->get('amount_usd'));
            if ($range && isset($range->from)) {
                $query->where('amount_usd', '>=', (float) $range->from);
            }
            if ($range && isset($range->to)) {
                $query->where('amount_usd', '<=', (float) $range->to);
            }
        }
        
        if (request()->has('payment_date') && request()->get('payment_date')) {
            $dates = json_decode(request()->get('payment_date'));
            if ($dates && isset($dates->from)) {
                $fromDate = date('Y-m-d', strtotime($dates->from)) . ' 00:00:00';
                $query->where('payment_date', '>=', $fromDate);
            }
            if ($dates && isset($dates->to)) {
                $toDate = date('Y-m-d', strtotime($dates->to)) . ' 23:59:59';
                $query->where('payment_date', '<=', $toDate);
            }
        }
        
        return $query;
    }

    /**
     * Calculate payment statistics from a collection of payments.
     * 
     * @param \Illuminate\Support\Collection $payments
     * @return array
     */
    protected function calculatePaymentStats($payments)
    {

        $paidPayments = $payments->where('status', 'Paid');
        $pendingPayments = $payments->where('status', 'Pending');

        return [
            'paymentsCount' => $payments->count(),
            'totalAmountGel' => $payments->sum('amount_gel') ?? 0,
            'paidCount' => $paidPayments->count(),
            'paidAmountGel' => $paidPayments->sum('amount_gel') ?? 0,
            'pendingCount' => $pendingPayments->count(),
            'pendingAmountGel' => $pendingPayments->sum('amount_gel') ?? 0,
            'cashSum' => $payments->where('method', 'Cash')->sum('amount_gel') ?? 0,
            'transferSum' => $payments->where('method', 'Transfer')->sum('amount_gel') ?? 0,
            'terminalSum' => $payments->where('method', 'Terminal')->sum('amount_gel') ?? 0,
            'pmTransferSum' => $payments->where('method', 'PM Transfer')->sum('amount_gel') ?? 0,
        ];
    }

    /**
     * Add widgets for payment statistics (count, total amounts, paid/pending).
     * This widget considers active filters.
     * 
     * @return void
     */
    protected function addPaymentStatsWidget()
    {
        $query = $this->applyPaymentFilters(Payment::query());
        $payments = $query->get();
        $stats = $this->calculatePaymentStats($payments);
        
        // Create a row container and add widgets inside it
        Widget::add([
            'type' => 'div',
            'class' => 'row mb-3',
            'wrapper' => false,
            'content' => [
                [
                    'type' => 'progress',
                    'class' => 'card text-white bg-primary',
                    'value' => number_format($stats['paymentsCount']),
                    'description' => 'Total Payments',
                    'wrapper' => ['class' => 'col-3'],
                ],
                [
                    'type' => 'progress',
                    'class' => 'card text-white bg-success',
                    'value' => number_format($stats['totalAmountGel'], 2) . ' ₾',
                    'description' => 'Total Amount GEL',
                    'wrapper' => ['class' => 'col-3'],
                ],
                [
                    'type' => 'progress',
                    'class' => 'card text-white bg-secondary',
                    'value' => number_format($stats['paidCount']) . ' / ' . number_format($stats['pendingCount']),
                    'description' => 'Paid / Pending',
                    'hint' => number_format($stats['paidAmountGel'], 2) . ' ₾ / ' . number_format($stats['pendingAmountGel'], 2) . ' ₾',
                    'wrapper' => ['class' => 'col-3'],
                ],
            ],
        ])->to('before_content');

        Widget::add([
            'type' => 'div',
            'class' => 'row mb-3',
            'wrapper' => false,
            'content' => [
                [
                    'type' => 'progress',
                    'class' => 'card text-white bg-success',
                    'value' => number_format($stats['cashSum'], 2) . ' ₾',
                    'description' => 'Cash SUM',
                    'wrapper' => ['class' => 'col-3'],
                ],
                [
                    'type' => 'progress',
                    'class' => 'card text-white bg-info',
                    'value' => number_format($stats['transferSum'], 2) . ' ₾',
                    'description' => 'Transfer SUM',
                    'wrapper' => ['class' => 'col-3'],
                ],
                [
                    'type' => 'progress',
                    'class' => 'card text-white bg-warning',
                    'value' => number_format($stats['terminalSum'], 2) . ' ₾',
                    'description' => 'Terminal SUM',
                    'wrapper' => ['class' => 'col-3'],
                ],
                [
                    'type' => 'progress',
                    'class' => 'card text-white bg-dark',
                    'value' => number_format($stats['pmTransferSum'], 2) . ' ₾',
                    'description' => 'PM Transfer SUM',
                    'wrapper' => ['class' => 'col-3'],
                ],
            ],
        ])->to('before_content');
    }

    /**
     * Get payment statistics via AJAX (for dynamic widget updates).
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentStats()
    {
        $query = $this->applyPaymentFilters(Payment::query());
        $payments = $query->get();
        $stats = $this->calculatePaymentStats($payments);
        
        return response()->json($stats);
    }

    /**
     * Get client balance via AJAX.
     *
     * @param int $clientId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClientBalance($clientId)
    {
        $client = Client::find($clientId);

        if (!$client) {
            return response()->json(['error' => 'Client not found'], 404);
        }

        $balance = $client->calculateBalance();

        return response()->json([
            'balance' => $balance,
            'formatted' => number_format($balance, 2) . ' ₾'
        ]);
    }

    /**
     * Create a payment via AJAX request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createAjax()
    {
        $request = request();

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'order_id' => 'nullable|integer|exists:orders,id',
            'amount_gel' => 'required|numeric|min:0',
            'currency_rate' => 'required|numeric|min:0',
            'method' => 'required|in:Cash,Transfer,Terminal,PM Transfer',
            'type' => 'required|in:' . implode(',', array_keys(Payment::types())),
            'status' => 'required|in:Paid,Pending',
            'payment_date' => 'required|date',
            'file' => 'nullable|file|max:10240',
        ]);

        // Empty strings are converted to null by Laravel; guard just in case.
        $validated['order_id'] = $validated['order_id'] ?? null;

        try {
            // Safety net against duplicate submissions (rapid double-click, an Enter
            // keypress that slips past the button lock, or a proxy retry). If an
            // identical payment was created moments ago, return it instead of
            // inserting a second row. The short window still allows a genuine second
            // identical payment to be entered a few seconds apart.
            $recentDuplicate = Payment::where('client_id', $validated['client_id'])
                ->where('order_id', $validated['order_id'])
                ->where('amount_gel', $validated['amount_gel'])
                ->where('currency_rate', $validated['currency_rate'])
                ->where('method', $validated['method'])
                ->where('type', $validated['type'])
                ->where('status', $validated['status'])
                ->where('payment_date', \Carbon\Carbon::parse($validated['payment_date']))
                ->where('created_at', '>=', now()->subSeconds(10))
                ->first();

            if ($recentDuplicate) {
                return response()->json([
                    'success' => true,
                    'payment' => [
                        'id' => $recentDuplicate->id,
                    ],
                    'duplicate' => true,
                ]);
            }

            if ($request->hasFile('file')) {
                $validated['file'] = $request->file('file')->store('payments', 'public');
            }

            $payment = Payment::create($validated);

            return response()->json([
                'success' => true,
                'payment' => [
                    'id' => $payment->id,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment: ' . $e->getMessage()
            ], 422);
        }
    }
}

