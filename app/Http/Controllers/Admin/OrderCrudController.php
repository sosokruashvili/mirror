<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Prologue\Alerts\Facades\Alert;
use Backpack\CRUD\app\Library\Widget;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Piece;
use App\Models\Service;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use App\Models\CustomPrice;
use App\Models\Payment;
/**
 * Class OrderCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class OrderCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Cache key holding the last manually-entered USD rate, reused as the
     * default currency rate for subsequent order creation.
     */
    private const MANUAL_RATE_CACHE_KEY = 'order.manual_currency_rate';

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Order::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/order');
        CRUD::setEntityNameStrings('order', 'orders');
        
        // Enable export buttons
        $this->crud->enableExportButtons();
        // orders.js is loaded directly in the create/edit views with a cache-busting
        // version string (see order/create.blade.php and order/edit.blade.php) so that
        // browsers always pick up the latest version instead of a stale cached copy.
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
        // Enable bulk actions (checkboxes)
        $this->crud->enableBulkActions();
        
        // Add widgets for orders count and total price
        $this->addOrderStatsWidgets();
        
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
            'name' => 'authorUser',
            'label' => 'Author',
            'type' => 'relationship',
            'entity' => 'authorUser',
            'attribute' => 'name',
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
            'name' => 'paid',
            'label' => 'Paid',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if ($entry->paid) {
                    return '<span class="badge text-bg-success">Yes</span>';
                } else {
                    return '<span class="badge text-bg-danger">No</span>';
                }
            }
        ]);

        CRUD::addColumn([
            'name' => 'order_type',
            'label' => 'Order Type',
            'type' => 'custom_html',
            'value' => function ($entry) {
                return order_type_ge($entry->order_type);
            }
        ]);

        CRUD::addColumn([
            'name' => 'product_type',
            'label' => 'Product Type',
            'type' => 'custom_html',
            'value' => function ($entry) {
                return product_type_ge($entry->product_type);
            }
        ]);

        CRUD::addColumn([
            'name' => 'currency_rate',
            'label' => 'Currency Rate',
            'type' => 'number',
            'decimals' => 4,
        ]);
        
        CRUD::addColumn([
            'name' => 'price_gel',
            'label' => 'Price (GEL)',
            'type' => 'number',
            'decimals' => 0
        ]);

        CRUD::addColumn([
            'name' => 'paid_amount',
            'label' => 'Paid Amount',
            'type' => 'custom_html',
            'value' => function ($entry) {
                return number_format($entry->calculatePaidAmount(), 2) . ' ₾';
            },
        ]);

        CRUD::addColumn([
            'name' => 'confirm_date',
            'label' => 'Confirm Date',
            'type' => 'datetime',
        ]);

        // Add bulk delete button
        $this->crud->addButton('top', 'bulk_delete', 'view', 'crud::buttons.bulk_delete', 'end');
        
        // Remove default buttons and add custom ones that check status
        $this->crud->removeButton('update');
        $this->crud->removeButton('delete');
        
        // Add custom update button that only shows for draft/new orders
        $this->crud->addButton('line', 'update', 'view', 'crud::buttons.update_order', 'end');
        
        // Add custom delete button that only shows for draft/new orders
        $this->crud->addButton('line', 'delete', 'view', 'crud::buttons.delete_order', 'end');

        // Invoice button (opens in new tab)
        $this->crud->addButton('line', 'invoice', 'view', 'crud::buttons.invoice', 'end');

        // Pieces button (filtered by order id)
        $this->crud->addButton('line', 'pieces', 'view', 'crud::buttons.pieces', 'end');

        // Finish button (only show when status is ready)
        $this->crud->addButton('line', 'finish', 'view', 'crud::buttons.finish_order', 'end');
        
        // Add filters
        $this->addOrderFilters();
    }
    
    /**
     * Add filters to the orders list.
     * 
     * @return void
     */
    protected function addOrderFilters()
    {
        // ID filter
        CRUD::addFilter([
            'type' => 'text',
            'name' => 'id',
            'label' => 'ID',
        ],
        false,
        function ($value) {
            CRUD::addClause('where', 'id', $value);
        });
        
        // Client filter
        CRUD::addFilter([
            'type' => 'select2',
            'name' => 'client_id',
            'label' => 'Client',
        ],
        function () {
            return \App\Models\Client::all()->pluck('name', 'id')->toArray();
        },
        function ($value) {
            CRUD::addClause('where', 'client_id', $value);
        });

        // Author filter (orders.author stores the creating user's id)
        CRUD::addFilter([
            'type' => 'select2',
            'name' => 'author',
            'label' => 'Author',
        ],
        function () {
            return \App\Models\User::orderBy('name')->pluck('name', 'id')->toArray();
        },
        function ($value) {
            CRUD::addClause('where', 'author', $value);
        });

        CRUD::addFilter([
            'name' => 'created_at',
            'type' => 'date_range',
            'label' => 'Order Date Range',
        ],
        false,
        function ($value) {
            $dateRange = $this->parseCreatedAtDateRange($value);

            if (!empty($dateRange['from'])) {
                CRUD::addClause('where', 'created_at', '>=', $dateRange['from']->toDateTimeString());
            }

            if (!empty($dateRange['to'])) {
                CRUD::addClause('where', 'created_at', '<=', $dateRange['to']->toDateTimeString());
            }
        });
        
        // Status filter
        CRUD::addFilter([
            'type' => 'select2',
            'name' => 'status',
            'label' => 'Status',
        ],
        function () {
            return [
                'draft' => 'Draft',
                'new' => 'New',
                'working' => 'Working',
                'done' => 'Done',
                'finished' => 'Finished',
            ];
        },
        function ($value) {
            CRUD::addClause('where', 'status', $value);
        });
        
        // Order Type filter
        CRUD::addFilter([
            'type' => 'select2',
            'name' => 'order_type',
            'label' => 'Order Type',
        ],
        function () {
            return [
                'retail' => 'საცალო',
                'wholesale' => 'საბითუმო',
            ];
        },
        function ($value) {
            CRUD::addClause('where', 'order_type', $value);
        });
        
        // Product Type filter
        CRUD::addFilter([
            'type' => 'select2',
            'name' => 'product_type',
            'label' => 'Product Type',
        ],
        function () {
            return [
                'mirror' => 'სარკე',
                'glass' => 'შუშა',
                'lamix' => 'ლამექსი',
                'glass_pkg' => 'შუშაპაკეტი',
                'service' => 'მომსახურება',
            ];
        },
        function ($value) {
            CRUD::addClause('where', 'product_type', $value);
        });

        // Product filter (multiselect) — show only orders containing any of the
        // selected products.
        CRUD::addFilter([
            'type' => 'select2_multiple',
            'name' => 'products',
            'label' => 'Products',
        ],
        function () {
            return Product::orderBy('title')->pluck('title', 'id')->toArray();
        },
        function ($value) {
            $productIds = array_filter((array) json_decode($value, true));

            if (!empty($productIds)) {
                CRUD::addClause('whereHas', 'products', function ($query) use ($productIds) {
                    $query->whereIn('products.id', $productIds);
                });
            }
        });
        
        // Price range filter
        // Note: Since total price is calculated, this filters by service prices as approximation
        CRUD::addFilter([
            'type' => 'range',
            'name' => 'price_gel',
            'label' => 'Price (GEL)',
            'label_from' => 'Min price',
            'label_to' => 'Max price',
        ],
        false,
        function ($value) {
            $range = json_decode($value, true);
            
            if (is_array($range)) {
                if (isset($range['from']) && $range['from'] !== '') {
                    CRUD::addClause('whereHas', 'services', function($query) use ($range) {
                        $query->where('order_service.price_gel', '>=', $range['from']);
                    });
                }
                
                if (isset($range['to']) && $range['to'] !== '') {
                    CRUD::addClause('whereHas', 'services', function($query) use ($range) {
                        $query->where('order_service.price_gel', '<=', $range['to']);
                    });
                }
            }
        });
        
        // Paid filter
        CRUD::addFilter([
            'type' => 'dropdown',
            'name' => 'paid',
            'label' => 'Paid',
        ],
        [
            0 => 'No',
            1 => 'Yes',
        ],
        function ($value) {
            CRUD::addClause('where', 'paid', $value);
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
        // Set custom view for create operation
        $this->crud->setCreateView('vendor.backpack.crud.order.create');

        // Default the Save button to "Save and edit this item" so the user stays on the
        // form after saving instead of being sent back to the list. Runs for both the
        // create and update operations since setupUpdateOperation() calls this method.
        $this->crud->setOperationSetting('defaultSaveAction', 'save_and_edit');

        // Backpack remembers the last-used save action per operation in the session, and
        // that remembered value takes precedence over the default above — so an earlier
        // "Save and back" on the edit form would stick. Reset it to our default whenever
        // the form is *displayed* (GET), leaving the actual save request untouched.
        if ($this->crud->getRequest()->isMethod('get')) {
            session([$this->crud->getCurrentOperation() . '.saveAction' => 'save_and_edit']);
        }

        // Set validation rules
        $this->crud->setValidation(\App\Http\Requests\OrderRequest::class);
        
        CRUD::addField([
            'name' => 'order_type',
            'label' => 'Order Type',
            'type' => 'select_from_array',
            'options' => [
                'retail' => 'საცალო',
                'wholesale' => 'საბითუმო',
            ],
            'allows_null' => false,
            'default' => 'retail',
            'attributes' => [
                'required' => true,
            ]
        ]);

        CRUD::addField([
            'name' => 'client_id',
            'label' => 'Client',
            'type' => 'select2',
            'entity' => 'client',
            'attribute' => 'name_with_id',
            'model' => \App\Models\Client::class,
            'allows_null' => true,
            'default' => null,
            'attributes' => [
                'required' => true,
                'data-placeholder' => 'Select a client',
            ],
        ]);

        CRUD::addField([
            'name' => 'new_client_button',
            'type' => 'custom_html',
            'value' => '<button type="button" id="newClientBtn" class="btn btn-sm btn-outline-primary">
                <i class="la la-plus"></i> New Client
            </button>',
        ]);

        

        CRUD::addField([
            'name' => 'currency_rate',
            'label' => 'USD Rate',
            'type' => 'number',
            // Prefill with the manual rate remembered from the last order that
            // used one; fall back to the live NBG rate when none has been set.
            'default' => Cache::get(self::MANUAL_RATE_CACHE_KEY, Currency::exchangeRate()),
            'hint' => 'Actual current USD rate: ' . Currency::exchangeRate(),
            'attributes' => [
                'required' => true,
                'step' => '0.0001',
            ],
            'wrapper' => [
                'class' => 'form-group col-md-3',
            ],
        ]);

        

        CRUD::addField([
            'name' => 'product_type',
            'label' => 'Order Product Type',
            'type' => 'select_from_array',
            'options' => [
                'mirror' => 'სარკე',
                'glass' => 'შუშა',
                'lamix' => 'ლამექსი',
                'glass_pkg' => 'მინაპაკეტი',
                'service' => 'მომსახურება'
            ],
            'allows_null' => true,
            'default' => null,
            'attributes' => [
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name'       => 'products',
            'label'      => 'Products',
            'type'       => 'repeatable',
            'init_rows'  => 1,
            'min_rows'   => 1,
            'new_item_label' => 'New Product',
            'fields'  => [
                [
                    'name'    => 'product_id',
                    'label'   => 'Product',
                    'type'    => 'select2_from_array',
                    'options' => Product::all()->pluck('title', 'id')->toArray(),
                    'allows_null' => true,
                    'default' => null,
                    'placeholder' => 'Select a product',
                    'attributes' => [
                        'required' => true,
                    ],
                    'wrapper' => [
                        'class' => 'form-group col-md-4'
                    ],
                ],
                [
                    'name'    => 'price',
                    'label'   => 'Price (USD)',
                    'type'    => 'number',
                    'attributes' => [
                        'step' => '0.01',
                        'min' => '0',
                        'required' => true,
                    ],
                    'wrapper' => [
                        'class' => 'form-group col-md-2'
                    ],
                ],
            ],
            'hint' => 'Add products to this order (filtered by Order Product Type)',
        ]);

        // Nested Pieces → Services UI. Each piece card owns its own services list.
        // Rendered by resources/views/vendor/backpack/crud/fields/pieces_services.blade.php
        // and driven by public/assets/js/order-pieces-services.js. It submits the same flat
        // pieces[]/services[] payload that store()/update() below already understand.
        CRUD::addField([
            'name'  => 'pieces_services',
            'label' => 'Pieces',
            'type'  => 'pieces_services',
            'hint'  => 'Add pieces to this order. Each piece can have its own services.',
        ]);

        // Hidden on create: new orders always start as draft. Made a visible select on edit
        // (see setupUpdateOperation).
        CRUD::addField([
            'name' => 'status',
            'type' => 'hidden',
            'default' => 'draft',
            'value' => 'draft',
        ]);

        CRUD::addField([
            'name' => 'paid',
            'label' => 'Paid',
            'type' => 'checkbox',
            'default' => false,
        ]);

        CRUD::addField([
            'name' => 'add_payment_button',
            'type' => 'custom_html',
            'value' => '<button type="button" id="addPaymentBtn" class="btn btn-sm btn-outline-primary">
                <i class="la la-plus"></i> Add Payment
            </button>',
        ]);

        CRUD::addField([
            'name' => 'atachment',
            'label' => 'Atachment',
            'type' => 'upload',
            'upload' => true,
            'disk' => 'public',
            'attributes' => [
                'accept' => '.pdf,.png,.jpeg,.jpg',
            ],
            'hint' => 'Allowed types: PDF, PNG, JPEG, JPG',
        ]);

        CRUD::addField([
            'name' => 'expenses',
            'label' => 'Expenses (m²)',
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
            ],
            'hint' => 'Auto-calculated from piece width, height and quantity. You can override it manually if it does not match the real expense.',
        ]);

        CRUD::addField([
            'name' => 'comment',
            'label' => 'Comment',
            'type' => 'textarea',
            'attributes' => [
                'rows' => 3,
                'placeholder' => 'Optional notes for the production team...',
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
        // Set custom view for edit operation
        $this->crud->setEditView('vendor.backpack.crud.order.edit');
        
        $this->setupCreateOperation();
        
        $entry = $this->crud->getCurrentEntry();

        if ($entry && !in_array($entry->status, ['draft', 'new'], true)) {
            $this->crud->denyAccess('update');
        }
        
        // Make product_type readonly on edit page
        $this->crud->modifyField('product_type', [
            'attributes' => [
                'disabled' => 'disabled',
            ],
            'wrapper' => [
                'class' => 'form-group col-md-12 readonly-field'
            ],
            'hint' => 'Order Product Type cannot be changed after creation',
        ]);

        // Status is hidden on create (always draft); make it a visible, editable select on edit.
        $this->crud->modifyField('status', [
            'label' => 'Status',
            'type' => 'select_from_array',
            'options' => [
                'draft' => 'Draft',
                'new' => 'New',
                'working' => 'Working',
                'done' => 'Done',
                'ready' => 'Ready',
                'finished' => 'Finished',
            ],
            'allows_null' => false,
            'default' => 'draft',
        ]);

        // Populate products data for editing
        $this->crud->modifyField('products', [
            'value' => $entry->products->map(function($product) {
                $pivot = $product->pivot;
                return [
                    'product_id' => $product->id,
                    'price' => $pivot->price ?? null,
                ];
            })->toArray()
        ]);
        
        // Pieces & services are hydrated directly from the entry inside the
        // pieces_services custom field view.
        $this->crud->modifyField('expenses', [
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
            ],
            'hint' => 'Auto-calculated from pieces. You can override it manually if it does not match the real expense.',
        ]);
        
        // Show the saved order price (GEL) in a highlighted row near the end of the edit
        // form. Value comes straight from the stored price_gel column (read-only summary).
        CRUD::addField([
            'name' => 'order_price_summary',
            'type' => 'custom_html',
            'value' => '
                <div class="card border-primary shadow-sm mb-2" style="border-width:2px;">
                    <div class="card-body py-3">
                        <div class="text-muted text-uppercase small mb-1">Price (GEL)</div>
                        <div class="fw-bold text-primary" style="font-size:1.6rem;">'
                            . number_format((float) ($entry->price_gel ?? 0), 2) . ' ₾</div>
                    </div>
                </div>',
        ]);
        $this->crud->afterField('comment');
    }


    protected function setupShowOperation()
    {
        // Add styles for order preview page
        Widget::add()->type('style')->content('assets/css/order-preview.css');

        // Inline per-piece stage updater (used by the pieces column below).
        Widget::add()->type('script')->content('assets/js/piece-stage.js');
        
        // Use team preview view for team users
        if (backpack_user() && backpack_user()->hasRole('team')) {
            $this->crud->set('show.view', 'admin.team-order-preview');
        }
        
        // Add Confirm button (only show when status is draft)
        $this->crud->addButton('line', 'confirm', 'view', 'crud::buttons.confirm', 'end');

        // Invoice button (opens in new tab)
        $this->crud->addButton('line', 'invoice', 'view', 'crud::buttons.invoice', 'end');
        
        // Conditionally show edit/delete buttons.
        // Update is available while the order is draft or new; delete is
        // restricted by status and role (draft: anyone with delete access,
        // new: administrators only).
        $entry = $this->crud->getCurrentEntry();
        if ($entry && !in_array($entry->status, ['draft', 'new'], true)) {
            $this->crud->denyAccess('update');
        }
        if ($entry && !$entry->canBeDeletedBy(backpack_user())) {
            $this->crud->denyAccess('delete');
        }
        
        CRUD::addColumn([
            'name' => 'id',
            'label' => 'ID',
            'type' => 'number',
        ]);
        
        CRUD::addColumn([
            'name' => 'client_id',
            'label' => 'Client',
            'type' => 'relationship',
            'entity' => 'client',
            'attribute' => 'name_with_id',
            'limit' => 9999, // Show full text without truncation
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
            'name' => 'paid',
            'label' => 'Paid',
            'type' => 'custom_html',
            'value' => function ($entry) {
                if ($entry->paid) {
                    return '<span class="badge text-bg-success">Yes</span>';
                } else {
                    return '<span class="badge text-bg-danger">No</span>';
                }
            }
        ]);

        CRUD::addColumn([
            'name' => 'comment',
            'label' => 'Comment',
            'type' => 'text',
            'limit' => 1000,
        ]);

        CRUD::addColumn([
            'name' => 'order_type',
            'label' => 'Order Type',
            'type' => 'custom_html',
            'value' => function ($entry) {
                return order_type_ge($entry->order_type);
            }
        ]);
        
        CRUD::addColumn([
            'name' => 'product_type',
            'label' => 'Product Type',
            'type' => 'custom_html',
            'value' => function ($entry) {
                return product_type_ge($entry->product_type);
            }
        ]);
        

        CRUD::addColumn([
            'name' => 'products',
            'label' => 'Products',
            'type' => 'custom_html',
            'value' => function ($entry) {
                $products = $entry->products;
                if ($products->isEmpty()) {
                    return '<span class="text-muted">' . trans('messages.no_products', [], 'ka') . '</span>';
                }
                
                $productRoute = url(config('backpack.base.route_prefix') . '/product');
                $links = $products->map(function ($product) use ($productRoute, $entry) {
                    $url = $productRoute . '?order_id=' . $entry->id;
                    $productInfo = 'ID: ' . $product->id . ' - ' . $product->title;
                    return '<a href="' . $url . '" target="_blank" class="d-block mb-1">' . htmlspecialchars($productInfo, ENT_QUOTES, 'UTF-8') . ' <i class="la la-external-link"></i></a>';
                })->implode('');
                
                return '<div>' . $links . '</div>';
            }
        ]);

        CRUD::addColumn([
            'name' => 'currency_rate',
            'label' => 'Currency Rate',
            'type' => 'number',
            'decimals' => 4,
        ]);


        CRUD::addColumn([
            'name' => 'price_gel',
            'label' => 'Price (GEL)',
            'type' => 'number',
            'value' => function ($entry) {  
                return $entry->calculateTotalPrice();
            },
            'decimals' => 0,
        ]);

        CRUD::addColumn([
            'name' => 'paid_amount',
            'label' => 'Paid Amount',
            'type' => 'custom_html',
            'value' => function ($entry) {
                $amount = number_format($entry->calculatePaidAmount(), 2) . ' ₾';
                $url = url(config('backpack.base.route_prefix') . '/payment?order_id=' . $entry->id);
                return $amount . ' <a href="' . $url . '" target="_blank" class="ms-1" title="View payment">Payment <i class="la la-external-link"></i></a>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'pieces',
            'label' => 'Pieces',
            'type' => 'custom_html',
            'value' => function ($entry) {
                $entry->load(['pieces.product', 'services']);
                if ($entry->pieces->isEmpty() && $entry->services->isEmpty()) {
                    return '<span>No pieces</span>';
                }
                
                $numericFields = ['price_gel', 'distance', 'length_cm', 'perimeter', 'area', 'foam_length', 'tape_length'];
                
                // Helper to format field value
                $formatValue = function($field, $value) use ($numericFields) {
                    if (in_array($field, $numericFields) && is_numeric($value)) {
                        return number_format((float)$value, 2, '.', '');
                    }
                    return $value;
                };
                
                // Helper to render service
                $renderService = function($service, $entry) use ($formatValue) {
                    $pivot = $service->pivot;
                    $html = '<div class="mb-2 p-2 service-item border rounded">';
                    $html .= '<div class="d-block mb-1 fw-bold">' . htmlspecialchars($service->title, ENT_QUOTES, 'UTF-8') . '</div>';
                    
                    // Get fields to display and their labels from helper function
                    $extraFields = get_service_extra_fields($service);
                    $fieldsToDisplay = $extraFields['fields'];
                    $fieldLabels = $extraFields['labels'];
                    
                    $pivotFields = [];
                    foreach ($fieldsToDisplay as $field) {
                        if (isset($pivot->$field) && $pivot->$field !== null && $pivot->$field !== '') {
                            $value = $formatValue($field, $pivot->$field);
                            $label = $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field));
                            $pivotFields[] = '<span>' . $label . ': </span><span>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</span>';
                        }
                    }
                    
                    if (!empty($pivotFields)) {
                        $html .= '<div class="small">' . implode(' | ', $pivotFields) . '</div>';
                    }
                    return $html . '</div>';
                };
                
                $html = '<div class="pieces-list">';

                // Lookups for filtering each piece's stage dropdown to only the
                // stages relevant to it: the universal stages plus the stages of
                // the services attached to that piece (services.stage_id).
                $allStageLabels = piece_stages();
                $stageOrderSlugs = array_keys($allStageLabels);
                $universalStageSlugs = array_keys(piece_universal_stages());
                $stageSlugById = \App\Models\Stage::ordered()->pluck('name', 'id');

                // Render pieces with their services
                foreach ($entry->pieces as $piece) {
                    $pieceServices = $entry->services->filter(fn($s) => $s->pivot->piece_id == $piece->id);

                    // Stages this piece can move through, in canonical order.
                    $serviceStageSlugs = $pieceServices
                        ->map(fn($s) => $stageSlugById[$s->stage_id] ?? null)
                        ->filter()
                        ->all();
                    $pieceStageSlugs = array_values(array_filter(
                        $stageOrderSlugs,
                        fn($slug) => in_array($slug, $serviceStageSlugs, true) || in_array($slug, $universalStageSlugs, true)
                    ));
                    
                    $html .= '<div class="mb-3 p-3 border rounded piece-item">';
                    $html .= '<div class="fw-bold d-block mb-2">Piece #' . $piece->id . ($piece->product ? ' - ' . htmlspecialchars($piece->product->title, ENT_QUOTES, 'UTF-8') : '') . '</div>';
                    $html .= '<div class="mb-2 small">';
                    $html .= '<span>Size: </span><strong>' . number_format($piece->width, 2) . ' × ' . number_format($piece->height, 2) . ' cm</strong> | ';
                    $html .= '<span>Quantity: </span><strong>' . $piece->quantity . '</strong> | ';
                    $html .= '<span>Area: </span><strong>' . number_format($piece->getArea(), 2) . ' m²</strong>';
                    $html .= '</div>';

                    // Per-piece production stage selector (saves inline via AJAX).
                    // "Completed through" the highest completed stage (piece_stage pivot).
                    $pieceStage = $piece->currentStageName();
                    $html .= '<div class="mb-2 d-print-none">';
                    $html .= '<label class="small me-2 fw-bold">Stage (ეტაპი):</label>';
                    $html .= '<select class="form-select form-select-sm d-inline-block" style="width:auto;" data-piece-stage-select data-piece-id="' . $piece->id . '">';
                    $html .= '<option value="">—</option>';
                    foreach ($pieceStageSlugs as $slug) {
                        $label = $allStageLabels[$slug] ?? $slug;
                        $selected = $pieceStage === $slug ? ' selected' : '';
                        $html .= '<option value="' . $slug . '"' . $selected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    $html .= '</select>';
                    // Non-JS / print fallback showing the highest completed stage label.
                    $html .= '<span class="d-none d-print-inline">' . htmlspecialchars(piece_stage_ge($pieceStage), ENT_QUOTES, 'UTF-8') . '</span>';
                    $html .= '</div>';
                    
                    if ($pieceServices->count() > 0) {
                        $html .= '<div class="mt-2 pt-2 border-top"><div class="small mb-2">Services (' . $pieceServices->count() . '):</div>';
                        foreach ($pieceServices as $service) $html .= $renderService($service, $entry);
                        $html .= '</div>';
                    } else {
                        $html .= '<div class="mt-2 pt-2 border-top small">No services assigned to this piece</div>';
                    }
                    $html .= '</div>';
                }
                
                return $html . '</div>';
            }
        ]);

        
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        return DB::transaction(function () {
            $fields = request()->all();

            // Remember the manual USD rate so it prefills future orders until
            // someone enters a different one.
            if (isset($fields['currency_rate']) && $fields['currency_rate'] !== '') {
                Cache::forever(self::MANUAL_RATE_CACHE_KEY, $fields['currency_rate']);
            }

            $order = Order::create(
                [
                    'order_type' => $fields['order_type'],
                    'client_id' => $fields['client_id'],
                    'status' => $fields['status'],
                    'product_type' => $fields['product_type'],
                    'currency_rate' => $fields['currency_rate'],
                    'author' => backpack_user()->id,
                    'paid' => isset($fields['paid']) ? (bool)$fields['paid'] : false,
                    'atachment' => $fields['atachment'] ?? null,
                    'comment' => $fields['comment'] ?? null,
                ]
            );

            // Attach products (one pivot row per line so identical products can appear multiple times)
            if (!empty($fields['products'])) {
                $order->products()->detach();
                foreach ($fields['products'] as $product) {
                    if (empty($product['product_id'])) {
                        continue;
                    }
                    if ($product['price'] !== Product::find($product['product_id'])->price) {
                        CustomPrice::updateOrCreate([
                            'client_id' => $order->client_id,
                            'product_id' => $product['product_id'],
                        ], [
                            'price_usd' => $product['price'],
                        ]);
                    }
                    $order->products()->attach($product['product_id'], [
                        'price' => $product['price'] ?? null,
                    ]);
                }
            }

            // Create pieces first (hasMany - delete existing and create new)
            // This allows us to map temporary piece IDs to real piece IDs for services
            $pieceIdMap = []; // Maps temporary IDs (temp_rowNumber) to real piece IDs
            if (!empty($fields['pieces'])) {
                $order->pieces()->delete();
                $pieceIndex = 0;
                foreach ($fields['pieces'] as $piece) {
                    $createdPiece = $order->pieces()->create([
                        'width' => $piece['width'] ?? 0,
                        'height' => $piece['height'] ?? 0,
                        'quantity' => $piece['quantity'] ?? 1,
                    ]);
                    // Map temporary ID to real ID (using index as key)
                    $tempId = 'temp_' . $pieceIndex;
                    $pieceIdMap[$tempId] = $createdPiece->id;
                    $pieceIndex++;
                }
            }

            // Attach services (many-to-many with pivot data) allowing duplicates and preserving order inputs
            $order->services()->detach();
            if (!empty($fields['services'])) {
                foreach ($fields['services'] as $service) {
                    if (empty($service['service_id'])) {
                        continue;
                    }
                    
                    // Map temporary piece ID to real piece ID if needed
                    $pieceId = $service['piece_id'] ?? null;
                    if ($pieceId && strpos($pieceId, 'temp_') === 0) {
                        $pieceId = $pieceIdMap[$pieceId] ?? null;
                    }
                    
                    // Calculate price_gel if not provided
                    $priceGel = $service['price_gel'] ?? null;
                    if ($priceGel === null || $priceGel === '') {
                        $serviceModel = Service::find($service['service_id']);
                        if ($serviceModel) {
                            $priceGel = $this->calculateServicePriceGel($serviceModel, $service);
                        } else {
                            $priceGel = 0;
                        }
                    }
                    
                    $order->services()->attach($service['service_id'], [
                        'piece_id' => $pieceId,
                        'quantity' => $service['quantity'] ?? null,
                        'description' => $service['description'] ?? null,
                        'color' => $service['color'] ?? null,
                        'light_type' => $service['light_type'] ?? null,
                        'price_gel' => $priceGel,
                        'distance' => $service['distance'] ?? null,
                        'length_cm' => $service['length_cm'] ?? null,
                        'perimeter' => $service['perimeter'] ?? null,
                        'area' => $service['area'] ?? null,
                        'antifog_type' => $service['antifog_type'] ?? null,
                        'foam_length' => $service['foam_length'] ?? null,
                        'tape_length' => $service['tape_length'] ?? null,
                        'sensor_type' => $service['sensor_type'] ?? null,
                        'sensor_quantity1' => $service['sensor_quantity1'] ?? null,
                    ]);
                }
            }

            // Refresh relationships to ensure they're loaded
            $order->refresh();
            $order->load(['services', 'products', 'pieces']);

            // Use a manually entered expense if provided, otherwise auto-calculate
            // from pieces (draft pieces are excluded by calculateExpenses()).
            $submittedExpenses = $fields['expenses'] ?? null;
            $order->expenses = ($submittedExpenses !== null && $submittedExpenses !== '')
                ? $submittedExpenses
                : $order->calculateExpenses();
            $order->save();

            $order->calculateOrderPrice();

            // Link payments that were created from the modal on this create page. The order did
            // not exist when those payments were made, so the JS stored their ids and we now
            // attach them to the freshly created order. Only claim still-unlinked payments.
            $createdPaymentIds = array_filter((array) ($fields['created_payment_ids'] ?? []));
            if (!empty($createdPaymentIds)) {
                Payment::whereIn('id', $createdPaymentIds)
                    ->whereNull('order_id')
                    ->update(['order_id' => $order->id]);
            }

            return $this->crud->performSaveAction($order->getKey());
        });
    }

    /**
     * Update the specified resource in storage.
     */
    public function update()
    {
        return DB::transaction(function () {
            $fields = request()->all();
            $order = $this->crud->getCurrentEntry();

            if (!in_array($order->status, ['draft', 'new'], true)) {
                abort(403, 'Only draft or new orders can be edited.');
            }

            // Update order basic fields
            $order->update([
                'order_type' => $fields['order_type'],
                'client_id' => $fields['client_id'],
                'status' => $fields['status'],
                'currency_rate' => $fields['currency_rate'],
                'paid' => isset($fields['paid']) ? (bool)$fields['paid'] : false,
                'atachment' => $fields['atachment'] ?? null,
                'comment' => $fields['comment'] ?? null,
                'expenses' => $fields['expenses'] ?? null,
            ]);

            // Attach products (one pivot row per line so identical products can appear multiple times)
            $order->products()->detach();
            if (!empty($fields['products'])) {
                foreach ($fields['products'] as $product) {
                    if (empty($product['product_id'])) {
                        continue;
                    }
                    if ($product['price'] !== Product::find($product['product_id'])->price) {
                        CustomPrice::updateOrCreate([
                            'client_id' => $order->client_id,
                            'product_id' => $product['product_id'],
                        ], [
                            'price_usd' => $product['price'],
                        ]);
                    }
                    $order->products()->attach($product['product_id'], [
                        'price' => $product['price'] ?? null,
                    ]);
                }
            }

            // Sync pieces (hasMany - delete existing and create new) and keep a map of old/temp IDs to new IDs
            $pieceIdMap = [];
            // The order edit form does not manage stages (that's done via the Piece CRUD and
            // the preview page), but pieces are deleted and recreated here — so capture the
            // completed-stage pivot (with completion dates) per piece id first and carry it
            // over to the freshly created pieces.
            $existingCompletions = $order->pieces()->with('stages')->get()
                ->mapWithKeys(function ($piece) {
                    return [$piece->id => $piece->stages->map(function ($stage) {
                        return [
                            'stage_id' => $stage->id,
                            'completed_at' => $stage->pivot->completed_at,
                            'user_id' => $stage->pivot->user_id,
                        ];
                    })->all()];
                })->toArray();
            if (!empty($fields['pieces'])) {
                $order->pieces()->delete();
                $pieceIndex = 0;
                foreach ($fields['pieces'] as $piece) {
                    $createdPiece = $order->pieces()->create([
                        'width' => $piece['width'] ?? 0,
                        'height' => $piece['height'] ?? 0,
                        'quantity' => $piece['quantity'] ?? 1,
                    ]);
                    // Re-attach the completed-stage pivot records (with their original
                    // completion dates) to the recreated piece.
                    if (!empty($piece['id']) && !empty($existingCompletions[$piece['id']])) {
                        foreach ($existingCompletions[$piece['id']] as $completion) {
                            $createdPiece->stages()->attach($completion['stage_id'], [
                                'completed_at' => $completion['completed_at'],
                                'user_id' => $completion['user_id'],
                            ]);
                        }
                    }
                    // Map both temporary ids (temp_#) and existing ids to the freshly created piece id
                    $tempId = 'temp_' . $pieceIndex;
                    $pieceIdMap[$tempId] = $createdPiece->id;
                    if (!empty($piece['id'])) {
                        $pieceIdMap[(string) $piece['id']] = $createdPiece->id;
                    }
                    $pieceIndex++;
                }
            } else {
                $order->pieces()->delete();
            }

            // Attach services (many-to-many with pivot data) using the piece ID map to keep selections intact; allow duplicates
            $order->services()->detach();
            if (!empty($fields['services'])) {
                foreach ($fields['services'] as $service) {
                    if (empty($service['service_id'])) {
                        continue;
                    }

                    $pieceId = $service['piece_id'] ?? null;
                    if ($pieceId) {
                        if (strpos((string) $pieceId, 'temp_') === 0 && isset($pieceIdMap[$pieceId])) {
                            $pieceId = $pieceIdMap[$pieceId];
                        } elseif (isset($pieceIdMap[(string) $pieceId])) {
                            $pieceId = $pieceIdMap[(string) $pieceId];
                        }
                    }

                    // Calculate price_gel if not provided
                    $priceGel = $service['price_gel'] ?? null;
                    if ($priceGel === null || $priceGel === '') {
                        $serviceModel = Service::find($service['service_id']);
                        if ($serviceModel) {
                            $priceGel = $this->calculateServicePriceGel($serviceModel, $service);
                        } else {
                            $priceGel = 0;
                        }
                    }

                    $order->services()->attach($service['service_id'], [
                        'piece_id' => $pieceId,
                        'quantity' => $service['quantity'] ?? null,
                        'description' => $service['description'] ?? null,
                        'color' => $service['color'] ?? null,
                        'light_type' => $service['light_type'] ?? null,
                        'price_gel' => $priceGel,
                        'distance' => $service['distance'] ?? null,
                        'length_cm' => $service['length_cm'] ?? null,
                        'perimeter' => $service['perimeter'] ?? null,
                        'area' => $service['area'] ?? null,
                        'antifog_type' => $service['antifog_type'] ?? null,
                        'foam_length' => $service['foam_length'] ?? null,
                        'tape_length' => $service['tape_length'] ?? null,
                        'sensor_type' => $service['sensor_type'] ?? null,
                        'sensor_quantity1' => $service['sensor_quantity1'] ?? null,
                    ]);
                }
            }

            // Refresh relationships to ensure they're loaded
            $order->refresh();
            $order->load(['services', 'products', 'pieces']);

            // Calculate order price after all relationships are set up
            $order->calculateOrderPrice();

            return $this->crud->performSaveAction($order->getKey());
        });
    }

    /**
     * Delete a single order.
     *
     * Draft orders may be deleted by any user with delete access; orders in
     * the "new" status may only be deleted by administrators. Orders past the
     * "new" stage cannot be deleted at all.
     */
    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');

        $order = Order::findOrFail($id);
        if (!$order->canBeDeletedBy(backpack_user())) {
            return response('You do not have permission to delete this order.', 403);
        }

        return (string) (int) $this->crud->delete($id);
    }

    /**
     * Bulk delete entries.
     */
    public function bulkDelete()
    {
        $this->crud->hasAccessOrFail('delete');

        $entries = $this->crud->getRequest()->input('entries');

        if (empty($entries)) {
            return response()->json(['message' => 'No entries selected.'], 400);
        }

        $user = backpack_user();
        $deleted = 0;
        $skipped = 0;
        foreach ($entries as $id) {
            $order = Order::find($id);

            // Enforce the same per-order status/role rule as single delete;
            // orders the user may not delete are silently skipped.
            if (!$order || !$order->canBeDeletedBy($user)) {
                $skipped++;
                continue;
            }

            if ($this->crud->delete($id)) {
                $deleted++;
            }
        }

        $message = $deleted . ' ' . ($deleted === 1 ? 'entry' : 'entries') . ' deleted successfully.';
        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' ' . ($skipped === 1 ? 'entry was' : 'entries were')
                . ' skipped (only draft orders can be deleted; new orders require an administrator).';
        }

        return response()->json([
            'message' => $message,
            'deleted' => $deleted,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Calculate order service price based on service data and field values.
     * 
     * @param Service $service The service model
     * @param array $serviceData The service data array with fields like perimeter, area, etc.
     * @return float The calculated price in GEL
     */
    private function calculateServicePriceGel(Service $service, array $serviceData)
    {
        $price_gel = 0;

        switch($service->slug) {
            case 'ek':
            case 'fk':
            case 'oval':
                $price_gel = ($serviceData['perimeter'] ?? 0) * $service->getPriceGel();
                break;
            case 'alum_frame':
            case 'rubber_frame':
                $price_gel = ($serviceData['perimeter'] ?? 0) * $service->getPriceGel() + 15;
                break;
            case 'training':
            case 'matt':
                $price_gel = ($serviceData['area'] ?? 0) * $service->getPriceGel();
                break;
            case 'delivery':
                $price_gel = ($serviceData['distance'] ?? 0) * $service->getPriceGel();
                break;
            case 'cutout':
                $price_gel = ($serviceData['length_cm'] ?? 0) * $service->getPriceGel();
                break;
            case 'hole':
            case 'hingcut':
            case 'hanger':
            case 'silicone':
            case 'stick':
                $price_gel = ($serviceData['quantity'] ?? 0) * $service->getPriceGel();
                break;
            case 'antifog':
                $price_gel = $service->getPriceGel();
                break;
            case 'light':
                $price_gel = ($serviceData['tape_length'] ?? 0) * 20 + ($serviceData['foam_length'] ?? 0) * 5 + ($serviceData['sensor_quantity1'] ?? 0) * 50 + 50;
                break;
            case 'kutxis_momrgvaleba':
                $price_gel = ($serviceData['quantity'] ?? 0) * $service->getPriceGel();
                break;
            default:
                $price_gel = 0;
                break;
        }

        return round($price_gel, 2);
    }

    /**
     * Calculate order service price based on service data and field values.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculate_order_service_price()
    {
        // Get all form data from the request
        $formData = request()->all();
        $service = Service::find($formData['service_id']);

        $price_gel = $this->calculateServicePriceGel($service, $formData);

        return response()->json([
            'price_gel' => $price_gel
        ]);
    }

    /**
     * Confirm an order (change status from draft to new).
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm($id)
    {
        $order = $this->crud->getEntry($id);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }
        
        if ($order->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only draft orders can be confirmed'
            ], 400);
        }
        
        $order->update([
            'status' => 'new',
            'confirm_date' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Order confirmed successfully'
        ]);
    }

    /**
     * Finish a ready order and all of its pieces.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function finish($id)
    {
        $order = $this->crud->getEntry($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        if ($order->status !== 'ready') {
            return response()->json([
                'success' => false,
                'message' => 'Only ready orders can be finished',
            ], 400);
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'finished']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Order finished successfully',
        ]);
    }

    /**
     * Update the production stage of a single piece (AJAX).
     *
     * Works regardless of the order's status so stages can be advanced during
     * production. `stage` may be empty to clear it back to "not set".
     *
     * @param int $id Piece ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePieceStage($id)
    {
        $piece = Piece::find($id);

        if (!$piece) {
            return response()->json([
                'success' => false,
                'message' => 'Piece not found',
            ], 404);
        }

        $stageSlug = request()->input('stage');
        $stageSlug = ($stageSlug === '' || $stageSlug === null) ? null : $stageSlug;

        // This single-select editor means "the piece has completed through this
        // stage", so mark every stage up to and including it as completed in the
        // piece_stage pivot (and refresh the pieces.stage cache).
        if ($stageSlug === null) {
            $piece->setCompletedThroughStage(null);
        } else {
            $stage = \App\Models\Stage::where('name', $stageSlug)->first();

            if (!$stage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid stage',
                ], 422);
            }

            $piece->setCompletedThroughStage($stage);
        }

        $currentStage = $piece->currentStageName();

        return response()->json([
            'success' => true,
            'piece_id' => $piece->id,
            'stage' => $currentStage,
            'stage_label' => piece_stage_ge($currentStage),
        ]);
    }

    /**
     * Add widgets for order statistics (count and total price).
     * 
     * @return void
     */
    protected function addOrderStatsWidgets()
    {
        // Get the model and start building query
        $query = Order::query();
        
        // Apply filters from request (mimic what CRUD filters do)
        if (request()->has('id') && request()->get('id')) {
            $query->where('id', request()->get('id'));
        }
        
        if (request()->has('status') && request()->get('status')) {
            $query->where('status', request()->get('status'));
        } else {
            $query->where('status', '!=', 'draft');
        }
        
        if (request()->has('client_id') && request()->get('client_id')) {
            $query->where('client_id', request()->get('client_id'));
        }
        
        if (request()->has('order_type') && request()->get('order_type')) {
            $query->where('order_type', request()->get('order_type'));
        }
        
        if (request()->has('product_type') && request()->get('product_type')) {
            $query->where('product_type', request()->get('product_type'));
        }

        if (request()->has('products') && request()->get('products')) {
            $productIds = array_filter((array) json_decode(request()->get('products'), true));
            if (!empty($productIds)) {
                $query->whereHas('products', function ($q) use ($productIds) {
                    $q->whereIn('products.id', $productIds);
                });
            }
        }

        if (request()->has('created_at') && request()->get('created_at')) {
            $dateRange = $this->parseCreatedAtDateRange(request()->get('created_at'));

            if (!empty($dateRange['from'])) {
                $query->where('created_at', '>=', $dateRange['from']);
            }

            if (!empty($dateRange['to'])) {
                $query->where('created_at', '<=', $dateRange['to']);
            }
        }
        
        // Apply price filter if set
        if (request()->has('price_gel') && request()->get('price_gel')) {
            $range = json_decode(request()->get('price_gel'), true);
            if (is_array($range)) {
                if (isset($range['from']) && $range['from'] !== '') {
                    $query->whereHas('services', function($q) use ($range) {
                        $q->where('order_service.price_gel', '>=', $range['from']);
                    });
                }
                if (isset($range['to']) && $range['to'] !== '') {
                    $query->whereHas('services', function($q) use ($range) {
                        $q->where('order_service.price_gel', '<=', $range['to']);
                    });
                }
            }
        }
        
        // Get all filtered orders with relationships for price calculation
        $orders = $query->with(['pieces', 'products', 'services', 'payments'])->get();

        // Calculate orders count
        $ordersCount = $orders->count();

        // Payment summary: how much has been paid across the filtered orders and
        // how much is still left to pay. Paid amount comes from the payments
        // linked to each order (payments.order_id); the outstanding balance per
        // order is its value minus what's been paid, floored at 0 so overpaid
        // orders don't offset the underpaid ones.
        $totalPaid = 0.0;
        $totalUnpaid = 0.0;

        foreach ($orders as $order) {
            $orderValue = $order->calculateTotalPriceExcludingDraftPieces();
            $paid = $order->calculatePaidAmount();

            $totalPaid += $paid;
            $totalUnpaid += max(0, $orderValue - $paid);
        }

        // Add stats cards - pass data to view
        Widget::add([
            'type' => 'view',
            'view' => 'vendor.backpack.crud.widgets.order_stats',
            'wrapper' => ['class' => 'col-12'],
            'ordersCount' => $ordersCount,
            'totalPriceGel' => $orders->sum(function($order) {
                return $order->calculateTotalPriceExcludingDraftPieces();
            }),
            'totalExpenses' => $orders->sum(function($order) {
                return $order->calculateExpenses();
            }),
            'totalPaid' => $totalPaid,
            'totalUnpaid' => $totalUnpaid,
        ])->to('before_content');
    }

    /**
     * Get orders by client ID for AJAX requests.
     * Used by payment form to filter orders by selected client.
     * 
     * @param int $clientId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrdersByClient($clientId)
    {
        $orders = Order::where('client_id', $clientId)
            ->with(['pieces', 'products', 'services'])
            ->get()
            ->map(function ($order) {
                // Same figure as order_display (not stored price_gel, which can be stale/truncated).
                // Send as a 2-decimal string so JSON does not drop fractional digits (150.00 → 150).
                $price = round((float) $order->calculateTotalPrice(false), 2);

                return [
                    'id' => $order->id,
                    'text' => $order->order_display,
                    'price' => number_format($price, 2, '.', ''),
                ];
            });

        return response()->json($orders);
    }

    /**
     * Generate and display invoice for an order (opens in new tab).
     *
     * @param int $id Order ID
     * @return \Illuminate\View\View
     */
    public function invoice($id)
    {
        $order = Order::with(['client', 'services', 'products', 'pieces'])
            ->findOrFail($id);

        return view('admin.order-invoice', compact('order'));
    }

    /**
     * Parse a created_at date-range filter payload.
     *
     * @param string|null $rawDateRange JSON payload: {"from":"YYYY-MM-DD","to":"YYYY-MM-DD"}
     * @return array{from: ?\Illuminate\Support\Carbon, to: ?\Illuminate\Support\Carbon}
     */
    private function parseCreatedAtDateRange(?string $rawDateRange): array
    {
        $result = [
            'from' => null,
            'to' => null,
        ];

        if (empty($rawDateRange)) {
            return $result;
        }

        $dates = json_decode($rawDateRange, true);
        if (!is_array($dates)) {
            return $result;
        }

        if (!empty($dates['from'])) {
            try {
                $result['from'] = Carbon::parse($dates['from'])->startOfDay();
            } catch (\Throwable $exception) {
                // Ignore invalid date input and keep listing usable.
            }
        }

        if (!empty($dates['to'])) {
            try {
                $result['to'] = Carbon::parse($dates['to'])->endOfDay();
            } catch (\Throwable $exception) {
                // Ignore invalid date input and keep listing usable.
            }
        }

        return $result;
    }
}