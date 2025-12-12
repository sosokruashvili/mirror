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
                'Terminal' => 'Terminal',
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
        CRUD::addField([
            'name' => 'client_id',
            'label' => 'Client',
            'type' => 'select2',
            'entity' => 'client',
            'attribute' => 'name_with_id',
            'model' => \App\Models\Client::class,
            'allows_null' => true,
            'hint' => 'Select the client for this payment',
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ],
            'attributes' => [
                'id' => 'client_id_field',
            ]
        ]);

        CRUD::addField([
            'name' => 'method',
            'label' => 'Payment Method',
            'type' => 'select_from_array',
            'options' => [
                'Cash' => 'Cash',
                'Transfer' => 'Transfer',
                'Terminal' => 'Terminal',
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
     * Apply filters to payment query based on request parameters.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyPaymentFilters($query)
    {
        if (request()->has('client_id') && request()->get('client_id') !== '') {
            $query->where('client_id', request()->get('client_id'));
        }
        
        if (request()->has('method') && request()->get('method') !== '') {
            $query->where('method', request()->get('method'));
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
}

