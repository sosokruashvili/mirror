<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Prologue\Alerts\Facades\Alert;
use Backpack\CRUD\app\Library\Widget;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Service;

/**
 * Class OrderCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class OrderCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Order::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/order');
        CRUD::setEntityNameStrings('order', 'orders');
        
        // Enable export buttons
        $this->crud->enableExportButtons();
        Widget::add()->type('script')->content('assets/js/orders.js');
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
            'name' => 'status',
            'label' => 'Status',
            'type' => 'custom_html',
            'value' => function ($entry) {
                return status_badge($entry->status);
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
            'decimals' => 2,
            'value' => function ($entry) {
                return $entry->calculateTotalPrice();
            }
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'label' => 'Created At',
            'type' => 'datetime',
        ]);

        // Add bulk delete button
        $this->crud->addButton('top', 'bulk_delete', 'view', 'crud::buttons.bulk_delete', 'end');
        
        // Remove default buttons and add custom ones that check status
        $this->crud->removeButton('update');
        $this->crud->removeButton('delete');
        
        // Add custom update button that only shows for draft orders
        $this->crud->addButton('line', 'update', 'view', 'crud::buttons.update_order', 'end');
        
        // Add custom delete button that only shows for draft orders
        $this->crud->addButton('line', 'delete', 'view', 'crud::buttons.delete_order', 'end');
        
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
            'attribute' => 'name',
            'model' => \App\Models\Client::class,
            'allows_null' => false,
            'hint' => 'Select the client for this order',
            'attributes' => [
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'currency_rate',
            'label' => 'Current USD Rate',
            'type' => 'number',
            'default' => Currency::exchangeRate(),
            'attributes' => [
                'required' => true,
                'readonly' => true,
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
                    'type'    => 'select2',
                    'entity' => 'product',
                    'attribute' => 'title',
                    'model' => \App\Models\Product::class,
                    'allows_null' => false,
                    'data_source' => url('api/products-filtered'),
                    'placeholder' => 'Select a product',
                    'minimum_input_length' => 0,
                    'dependencies' => ['order_product_type'],
                    'method' => 'GET',
                ],
            ],
            'hint' => 'Add products to this order (filtered by Order Product Type)',
        ]);

        CRUD::addField([
            'name'       => 'services',
            'label'      => 'Services',
            'type'       => 'repeatable',
            'init_rows'  => 0,
            'min_rows'   => 0,
            'new_item_label' => 'Add Service',
            'fields'     => [
                [
                    'name'    => 'service_id',
                    'label'   => 'Service',
                    'type'    => 'select2',
                    'entity' => 'service',
                    'attribute' => 'title',
                    'model' => \App\Models\Service::class,
                    'allows_null' => true,
                    'default' => null,
                    'placeholder' => 'Select a service',
                    'minimum_input_length' => 0,
                    'wrapper' => [
                        'class' => 'form-group col-md-3'
                    ],
                ],
                [
                    'name' => 'quantity', 
                    'label' => 'Quantity', 
                    'type' => 'number', 
                    'wrapper' => [
                        'class' => 'form-group col-md-1 extra extra-qty price-calc d-none'
                    ]
                ],
                [
                    'name' => 'perimeter',
                    'label' => 'Perimeter (m)',
                    'type' => 'number',
                    'wrapper' => [
                        'class' => 'form-group col-md-2 extra extra-perimeter price-calc d-none'
                    ]
                ],
                [
                    'name'    => 'color',
                    'label'   => 'Color',
                    'type'    => 'select_from_array',
                    'options' => [
                        'ოქროსფერი' => 'ოქროსფერი',
                        'ვერცხლისფერი' => 'ვერცხლისფერი',
                        'წითელი' => 'წითელი',
                        'თეთრი' => 'თეთრი',
                        'შავი' => 'შავი',
                        'ლურჯი' => 'ლურჯი',
                        'მწვანე' => 'მწვანე',
                        'ნაცრისფერი' => 'ნაცრისფერი',
                    ],
                    'allows_null' => true,
                    'wrapper' => [
                        'class' => 'form-group col-md-2 extra extra-color price-calc d-none'
                    ],
                ],
                [
                    'name' => 'light_type', 
                    'label' => 'Light Type', 
                    'type' => 'select_from_array', 
                    'options' => [
                        'cool'      => 'ცივი',
                        'warm'      => 'თბილი',
                        'neutral'   => 'ნეიტრალური',
                    ],
                    'allows_null' => true,
                    'wrapper' => ['class' => 'form-group col-md-1 extra extra-light-type price-calc d-none']
                ],
                [
                    'name' => 'distance',
                    'label' => 'Delivery Distance (km)',
                    'type' => 'number',
                    'attributes' => [
                        'step' => '1',
                        'min' => '0',
                    ],
                    'wrapper' => [
                        'class' => 'form-group col-md-2 extra extra-distance price-calc d-none'
                    ],
                ],
                [
                    'name' => 'length_cm',
                    'label' => 'Length (cm)',
                    'type' => 'number',
                    'attributes' => [
                        'step' => '1',
                        'min' => '0',
                    ],
                    'wrapper' => [
                        'class' => 'form-group col-md-2 extra extra-length-cm price-calc d-none'
                    ],
                ],
                [
                    'name' => 'area',
                    'label' => 'Area (m²)',
                    'type' => 'number',
                    'wrapper' => [
                        'class' => 'form-group col-md-2 extra extra-area price-calc d-none'
                    ]
                ],
                [
                    'name' => 'tape_length',
                    'label' => 'Tape Length (m)',
                    'type' => 'number',
                    'wrapper' => [
                        'class' => 'form-group col-md-1 extra extra-tape-length price-calc d-none'
                    ]
                ],
                
                [
                    'name' => 'sensor_quantity1',
                    'label' => 'Sensor Quantity',
                    'type' => 'number',
                    'attributes' => [
                        'min' => '0',
                        'max' => '2',
                    ],
                    'wrapper' => [
                        'class' => 'form-group col-md-1 extra extra-sensor-quantity price-calc d-none'
                    ]
                ],
                [
                    'name' => 'sensor_type',
                    'label' => 'Sensor Type',
                    'type' => 'select_from_array',
                    'options' => [
                        'touch' => 'Touch',
                        'ir' => 'IR',
                    ],
                    'allows_null' => true,
                    'wrapper' => [
                        'class' => 'form-group col-md-1 extra extra-sensor-type price-calc d-none'
                    ]
                ],
                
                
                [
                    'name' => 'foam_length',
                    'label' => 'Foam Length (m)',
                    'type' => 'number',
                    'wrapper' => [
                        'class' => 'form-group col-md-1 extra extra-foam-length price-calc d-none'
                    ]
                ],
                
                [
                    'name' => 'price_gel',
                    'label' => 'Price (GEL)',
                    'type' => 'number',
                    'wrapper' => [
                        'class' => 'form-group col-md-2 extra extra-price d-none'
                    ]
                ],
                [
                    'name' => 'calculate_price_btn',
                    'type' => 'custom_html',
                    'value' => '<button type="button" class="btn btn-primary calculate-price-btn" style="margin-top: 30px;">Calculate Price</button>',
                    'wrapper' => [
                        'class' => 'form-group col-md-2'
                    ]
                ],
               
            ]
        ]);


        CRUD::addField([
            'name'       => 'pieces',
            'label'      => 'Pieces',
            'type'       => 'repeatable',
            'init_rows'  => 1,
            'min_rows'   => 1,
            'fields'     => [
                [
                    'name'      => 'width',
                    'label'     => 'Width (cm)',
                    'type'      => 'number',
                    'showAsterisk' => true,
                    'attributes' => [
                        'step' => '0.01',
                        'min' => '0',
                        'required' => true,
                    ],
                    'wrapper' => [
                        'class' => 'form-group col-md-4'
                    ],
                ],
                [
                    'name'      => 'height',
                    'label'     => 'Height (cm)',
                    'type'      => 'number',
                    'attributes' => [
                        'step' => '0.01',
                        'min' => '0',
                        'required' => true,
                    ],
                    'wrapper' => [
                        'class' => 'form-group col-md-4'
                    ],
                ],
                [
                    'name'      => 'quantity',
                    'label'     => 'Quantity',
                    'type'      => 'number',
                    'attributes' => [
                        'min' => '1',
                        'required' => true,
                    ],
                    'wrapper' => [
                        'class' => 'form-group col-md-4'
                    ],
                ],
            ],
            'hint' => 'Add pieces to this order',
        ]);

        

        CRUD::addField([
            'name' => 'status',
            'label' => 'Status',
            'type' => 'select_from_array',
            'options' => [
                'draft' => 'Draft',
                'new' => 'New',
                'working' => 'Working',
                'done' => 'Done',
                'finished' => 'Finished',
            ],
            'allows_null' => false,
            'default' => 'draft',
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
        
        // Populate products data for editing
        $this->crud->modifyField('products', [
            'value' => $entry->products->map(function($product) {
                return [
                    'product_id' => $product->id,
                ];
            })->toArray()
        ]);
        
        // Populate services data for editing with ALL pivot fields
        $this->crud->modifyField('services', [
            'value' => $entry->services->map(function($service) {
                $pivot = $service->pivot;
                return [
                    'service_id' => $service->id,
                    'quantity' => $pivot->quantity ?? null,
                    'description' => $pivot->description ?? null,
                    'color' => $pivot->color ?? null,
                    'light_type' => $pivot->light_type ?? null,
                    'price_gel' => $pivot->price_gel ?? null,
                    'distance' => $pivot->distance ?? null,
                    'length_cm' => $pivot->length_cm ?? null,
                    'perimeter' => $pivot->perimeter ?? null,
                    'area' => $pivot->area ?? null,
                    'antifog_type' => $pivot->antifog_type ?? null,
                    'foam_length' => $pivot->foam_length ?? null,
                    'tape_length' => $pivot->tape_length ?? null,
                    'sensor_type' => $pivot->sensor_type ?? null,
                    'sensor_quantity1' => $pivot->sensor_quantity1 ?? null,
                ];
            })->toArray()
        ]);
        
        // Populate pieces data for editing
        $this->crud->modifyField('pieces', [
            'value' => $entry->pieces->map(function($piece) {
                return [
                    'width' => $piece->width,
                    'height' => $piece->height,
                    'quantity' => $piece->quantity,
                    'product_id' => $piece->product_id ?? null,
                ];
            })->toArray()
        ]);
    }


    protected function setupShowOperation()
    {
        // Add Confirm button (only show when status is draft)
        $this->crud->addButton('line', 'confirm', 'view', 'crud::buttons.confirm', 'end');
        
        // Conditionally show edit/delete buttons only when status is draft
        $entry = $this->crud->getCurrentEntry();
        if ($entry && $entry->status !== 'draft') {
            $this->crud->denyAccess('update');
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
            'name' => 'pieces',
            'label' => 'Pieces',
            'type' => 'custom_html',
            'value' => function ($entry) {
                $pieces = $entry->pieces;
                if ($pieces->isEmpty()) {
                    return '<span class="text-muted">' . trans('messages.no_pieces', [], 'ka') . '</span>';
                }
                
                $pieceRoute = url(config('backpack.base.route_prefix') . '/piece');
                $links = $pieces->map(function ($piece) use ($pieceRoute, $entry) {
                    $url = $pieceRoute . '?order_id=' . $entry->id;
                    $pieceInfo = 'ID: ' . $piece->id . ' - ' . $piece->width . 'x' . $piece->height . ' (Qty: ' . $piece->quantity . ') (' . number_format($piece->width/1000*$piece->height/1000*$piece->quantity, 2, '.', '') . ' m²)';
                    return '<a href="' . $url . '" target="_blank" class="d-block mb-1">' . htmlspecialchars($pieceInfo, ENT_QUOTES, 'UTF-8') . ' <i class="la la-external-link"></i></a>';
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
            'decimals' => 2,
        ]);

        CRUD::addColumn([
            'name' => 'services',
            'label' => 'Services',
            'type' => 'custom_html',
            'value' => function ($entry) {
                $services = $entry->services;
                if ($services->isEmpty()) {
                    return '<span class="text-muted">' . trans('messages.no_services', [], 'ka') . '</span>';
                }
                
                $serviceRoute = url(config('backpack.base.route_prefix') . '/service');
                $serviceItems = $services->map(function ($service) use ($serviceRoute, $entry) {
                    $url = $serviceRoute . '?order_id=' . $entry->id;
                    $pivot = $service->pivot;
                    
                    // Build service info with link
                    $html = '<div class="mb-3 p-2 border rounded">';
                    $html .= '<a href="' . $url . '" target="_blank" class="d-block mb-2 fw-bold">';
                    $html .= 'ID: ' . $service->id . ' - ' . htmlspecialchars($service->title, ENT_QUOTES, 'UTF-8');
                    $html .= ' <i class="la la-external-link"></i>';
                    $html .= '</a>';
                    
                    // Display non-null pivot fields in a table
                    $pivotFields = [];
                    $fieldsToCheck = [
                        'quantity',
                        'description',
                        'color',
                        'light_type',
                        'distance',
                        'length_cm',
                        'perimeter',
                        'area',
                        'antifog_type',
                        'foam_length',
                        'tape_length',
                        'sensor_type',
                        'sensor_quantity',
                        'price_gel',
                    ];
                    
                    foreach ($fieldsToCheck as $field) {
                        if (isset($pivot->$field) && $pivot->$field !== null && $pivot->$field !== '') {
                            $value = $pivot->$field;
                            
                            // Format numeric values
                            if (in_array($field, ['price_gel', 'distance', 'length_cm', 'perimeter', 'area', 'foam_length', 'tape_length']) && is_numeric($value)) {
                                $value = number_format((float)$value, 2, '.', '');
                            }
                            
                            // Translate non-numeric values using messages.php
                            $translatableFields = ['light_type', 'antifog_type', 'sensor_type'];
                            if (in_array($field, $translatableFields) && !is_numeric($value)) {
                                $translationKey = 'messages.' . $field . '.' . $value;
                                $translatedValue = trans($translationKey, [], 'ka');
                                // If translation exists (not the same as key), use it
                                if ($translatedValue !== $translationKey) {
                                    $value = $translatedValue;
                                }
                            }
                            
                            // Use Laravel translation with Georgian locale for field label
                            $label = trans('service_pivot.' . $field, [], 'ka');
                            $pivotFields[] = [
                                'label' => $label,
                                'value' => htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
                            ];
                        }
                    }
                    
                    if (!empty($pivotFields)) {
                        $html .= '<table class="table table-sm table-bordered mb-0 mt-2">';
                        $html .= '<tbody>';
                        foreach ($pivotFields as $field) {
                            $html .= '<tr>';
                            $html .= '<td class="fw-bold" style="width: 40%;">' . $field['label'] . '</td>';
                            $html .= '<td>' . $field['value'] . '</td>';
                            $html .= '</tr>';
                        }
                        $html .= '</tbody>';
                        $html .= '</table>';
                    }
                    
                    $html .= '</div>';
                    return $html;
                })->implode('');
                
                return '<div>' . $serviceItems . '</div>';
            }
        ]);


    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        $fields = request()->all();

        $order = Order::create(
            [
                'order_type' => $fields['order_type'],
                'client_id' => $fields['client_id'],
                'status' => $fields['status'],
                'product_type' => $fields['product_type'],
                'currency_rate' => $fields['currency_rate'],
                'author' => backpack_user()->id,
            ]
        );
        
        // Sync products (many-to-many)
        if (!empty($fields['products'])) {
            $productIds = array_filter(array_column($fields['products'], 'product_id'));
            $order->products()->sync($productIds);
        }

        // Sync services (many-to-many with pivot data)
        if (!empty($fields['services'])) {
            $syncData = [];
            foreach ($fields['services'] as $service) {
                if (empty($service['service_id'])) {
                    continue;
                }
                $syncData[$service['service_id']] = [
                    'quantity' => $service['quantity'] ?? null,
                    'description' => $service['description'] ?? null,
                    'color' => $service['color'] ?? null,
                    'light_type' => $service['light_type'] ?? null,
                    'price_gel' => $service['price_gel'] ?? null,
                    'distance' => $service['distance'] ?? null,
                    'length_cm' => $service['length_cm'] ?? null,
                    'perimeter' => $service['perimeter'] ?? null,
                    'area' => $service['area'] ?? null,
                    'antifog_type' => $service['antifog_type'] ?? null,
                    'foam_length' => $service['foam_length'] ?? null,
                    'tape_length' => $service['tape_length'] ?? null,
                    'sensor_type' => $service['sensor_type'] ?? null,
                    'sensor_quantity1' => $service['sensor_quantity1'] ?? null,
                ];
            }
            $order->services()->sync($syncData);
        }

        // Sync pieces (hasMany - delete existing and create new)
        if (!empty($fields['pieces'])) {
            $order->pieces()->delete();
            foreach ($fields['pieces'] as $piece) {
                $order->pieces()->create([
                    'width' => $piece['width'] ?? 0,
                    'height' => $piece['height'] ?? 0,
                    'quantity' => $piece['quantity'] ?? 1,
                ]);
            }
        }

        // Refresh relationships to ensure they're loaded
        $order->refresh();
        $order->load(['services', 'products', 'pieces']);

        // Calculate order price after all relationships are set up
        $order->calculateOrderPrice();

        return $this->crud->performSaveAction($order->getKey());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update()
    {
        $fields = request()->all();
        $order = $this->crud->getCurrentEntry();

        // Update order basic fields
        $order->update([
            'order_type' => $fields['order_type'],
            'client_id' => $fields['client_id'],
            'status' => $fields['status'],
            'currency_rate' => $fields['currency_rate'],
        ]);

        // Sync products (many-to-many)
        if (!empty($fields['products'])) {
            $productIds = array_filter(array_column($fields['products'], 'product_id'));
            $order->products()->sync($productIds);
        } else {
            $order->products()->sync([]);
        }

        // Sync services (many-to-many with pivot data)
        if (!empty($fields['services'])) {
            $syncData = [];
            foreach ($fields['services'] as $service) {
                if (empty($service['service_id'])) {
                    continue;
                }
                $syncData[$service['service_id']] = [
                    'quantity' => $service['quantity'] ?? null,
                    'description' => $service['description'] ?? null,
                    'color' => $service['color'] ?? null,
                    'light_type' => $service['light_type'] ?? null,
                    'price_gel' => $service['price_gel'] ?? null,
                    'distance' => $service['distance'] ?? null,
                    'length_cm' => $service['length_cm'] ?? null,
                    'perimeter' => $service['perimeter'] ?? null,
                    'area' => $service['area'] ?? null,
                    'antifog_type' => $service['antifog_type'] ?? null,
                    'foam_length' => $service['foam_length'] ?? null,
                    'tape_length' => $service['tape_length'] ?? null,
                    'sensor_type' => $service['sensor_type'] ?? null,
                    'sensor_quantity1' => $service['sensor_quantity1'] ?? null,
                ];
            }
            $order->services()->sync($syncData);
        } else {
            $order->services()->sync([]);
        }

        // Sync pieces (hasMany - delete existing and create new)
        if (!empty($fields['pieces'])) {
            $order->pieces()->delete();
            foreach ($fields['pieces'] as $piece) {
                $order->pieces()->create([
                    'width' => $piece['width'] ?? 0,
                    'height' => $piece['height'] ?? 0,
                    'quantity' => $piece['quantity'] ?? 1,
                ]);
            }
        } else {
            $order->pieces()->delete();
        }

        return $this->crud->performSaveAction($order->getKey());
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

        $deleted = 0;
        foreach ($entries as $id) {
            if ($this->crud->delete($id)) {
                $deleted++;
            }
        }

        return response()->json([
            'message' => $deleted . ' ' . ($deleted === 1 ? 'entry' : 'entries') . ' deleted successfully.',
            'deleted' => $deleted
        ]);
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

        $price_gel = 0;

        switch($service->slug) {
            case 'ek':
            case 'fk':
            case 'oval':
                $price_gel = $formData['perimeter'] * $service->getPriceGel();
                break;
            case 'alum_frame':
            case 'rubber_frame':
                $price_gel = $formData['perimeter'] * $service->getPriceGel() + 15;
                break;
            case 'training':
            case 'matt':
                $price_gel = $formData['area'] * $service->getPriceGel();
                break;
            case 'delivery':
                $price_gel = $formData['distance'] * $service->getPriceGel();
                break;
            case 'cutout':
                $price_gel = $formData['length_cm'] * $service->getPriceGel();
                break;
            case 'hole':
            case 'hingcut':
            case 'hanger':
            case 'silicone':
            case 'stick':
                $price_gel = $formData['quantity'] * $service->getPriceGel();
                break;
            case 'antifog':
                $price_gel = $service->getPriceGel();
                break;
            case 'light':
                $price_gel = $formData['tape_length'] * 20 + $formData['foam_length'] * 5 + $formData['sensor_quantity1'] * 50 + 50;
                break;
            case 'default':
                $price_gel = 0;
                break;
        }

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
        
        $order->update(['status' => 'new']);
        
        return response()->json([
            'success' => true,
            'message' => 'Order confirmed successfully'
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
        $orders = $query->with(['pieces', 'products', 'services'])->get();
        
        // Calculate orders count
        $ordersCount = $orders->count();
        
        // Add both cards side by side - pass data to view
        Widget::add([
            'type' => 'view',
            'view' => 'vendor.backpack.crud.widgets.order_stats',
            'wrapper' => ['class' => 'col-12'],
            'ordersCount' => $ordersCount,
            'totalPriceGel' => $orders->sum(function($order) {
                return $order->price_gel;
            }),
        ])->to('before_content');
    }
}