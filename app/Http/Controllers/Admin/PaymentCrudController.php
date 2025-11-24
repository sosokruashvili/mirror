<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Models\Currency;
use App\Models\Client;

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
            'name' => 'order_id',
            'label' => 'Order',
            'type' => 'relationship',
            'entity' => 'order',
            'attribute' => 'order_display',
            'limit' => 9999,
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
            'name' => 'order_id',
            'label' => 'Order',
            'type' => 'select2',
            'entity' => 'order',
            'attribute' => 'order_display',
            'model' => \App\Models\Order::class,
            'allows_null' => true,
            'hint' => 'Select the order for this payment (filtered by selected client)',
            'options' => function ($query) {
                // Get the client_id from the request or old input
                $clientId = request()->input('client_id') ?? old('client_id');
                if ($clientId) {
                    return $query->where('client_id', $clientId)->get();
                }
                return collect([]);
            },
            'wrapper' => [
                'class' => 'form-group col-md-6'
            ],
            'attributes' => [
                'id' => 'order_id_field',
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

        // Add JavaScript to filter orders based on selected client
        CRUD::addField([
            'name' => 'order_filter_script',
            'type' => 'custom_html',
            'value' => '
                <script>
                (function() {
                    // Store orders data for price lookup (accessible across functions)
                    let ordersData = {};
                    
                    function initOrderFilter() {
                        const clientField = document.querySelector("#client_id_field");
                        const orderField = document.querySelector("#order_id_field");
                        
                        if (!clientField || !orderField) {
                            // Retry after a short delay if elements not found yet
                            setTimeout(initOrderFilter, 500);
                            return;
                        }
                        
                        // Function to set amount from selected order
                        function setAmountFromOrder(orderId) {
                            const amountField = document.querySelector("#amount_gel_field");
                            if (!amountField) return;
                            
                            if (!orderId || !ordersData[orderId]) {
                                return;
                            }
                            
                            const price = ordersData[orderId];
                            amountField.value = parseFloat(price).toFixed(2);
                            
                            // Trigger change event to update any dependent fields
                            if (typeof $ !== "undefined") {
                                $(amountField).trigger("change");
                            }
                        }
                        
                        // Function to load orders for selected client
                        function loadOrders(clientId) {
                            if (!clientId) {
                                // Clear orders if no client selected
                                if (typeof $ !== "undefined" && $(orderField).length && $(orderField).data("select2")) {
                                    $(orderField).empty().append(\'<option value="">-</option>\');
                                    $(orderField).val(null).trigger("change");
                                } else {
                                    orderField.innerHTML = \'<option value="">-</option>\';
                                }
                                return;
                            }
                            
                            // Show loading state
                            if (typeof $ !== "undefined" && $(orderField).length && $(orderField).data("select2")) {
                                $(orderField).prop("disabled", true);
                            }
                            
                            // Fetch orders for the selected client
                            fetch(\'/admin/order/get-orders-by-client/\' + clientId)
                                .then(response => response.json())
                                .then(data => {
                                    // Clear and store orders data for price lookup
                                    ordersData = {};
                                    data.forEach(function(order) {
                                        ordersData[order.id] = order.price;
                                    });
                                    
                                    // Clear existing options
                                    if (typeof $ !== "undefined" && $(orderField).length && $(orderField).data("select2")) {
                                        $(orderField).empty().append(\'<option value="">-</option>\');
                                        
                                        // Add new options
                                        data.forEach(function(order) {
                                            const option = new Option(order.text, order.id, false, false);
                                            $(orderField).append(option);
                                        });
                                        
                                        $(orderField).prop("disabled", false).trigger("change");
                                    } else {
                                        orderField.innerHTML = \'<option value="">-</option>\';
                                        data.forEach(function(order) {
                                            const option = document.createElement(\'option\');
                                            option.value = order.id;
                                            option.textContent = order.text;
                                            orderField.appendChild(option);
                                        });
                                    }
                                })
                                .catch(error => {
                                    console.error("Error loading orders:", error);
                                    if (typeof $ !== "undefined" && $(orderField).length) {
                                        $(orderField).prop("disabled", false);
                                    }
                                });
                        }
                        
                        // Wait for select2 to be initialized
                        setTimeout(function() {
                            // Load orders on client change
                            $(clientField).on("change", function() {
                                loadOrders($(this).val());
                                // Clear amount when client changes
                                const amountField = document.querySelector("#amount_gel_field");
                                if (amountField) {
                                    amountField.value = "";
                                }
                            });
                            
                            // Handle order selection change
                            $(orderField).on("change", function() {
                                const orderId = $(this).val();
                                setAmountFromOrder(orderId);
                            });
                            
                            // Load orders on page load if client is already selected
                            if ($(clientField).val()) {
                                loadOrders($(clientField).val());
                            }
                        }, 1000);
                    }
                    
                    // Initialize when DOM is ready
                    if (document.readyState === "loading") {
                        document.addEventListener("DOMContentLoaded", initOrderFilter);
                    } else {
                        initOrderFilter();
                    }
                })();
                </script>
            '
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

