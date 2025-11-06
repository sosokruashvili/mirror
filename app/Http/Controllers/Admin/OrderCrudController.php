<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Prologue\Alerts\Facades\Alert;
use Backpack\CRUD\app\Library\Widget;

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
        CRUD::addColumn([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'status',
            'label' => 'Status',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'order_type',
            'label' => 'Order Type',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'client_id',
            'label' => 'Client',
            'type' => 'select',
            'entity' => 'client',
            'attribute' => 'name',
        ]);

        CRUD::addColumn([
            'name' => 'products',
            'label' => 'Products',
            'type' => 'select_multiple',
            'entity' => 'products',
            'attribute' => 'title',
        ]);

        CRUD::addColumn([
            'name' => 'services',
            'label' => 'Services',
            'type' => 'select_multiple',
            'entity' => 'services',
            'attribute' => 'title',
        ]);
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
                'retail' => 'Retail',
                'wholesale' => 'Wholesale',
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
            'allows_null' => true,
            'hint' => 'Select the client for this order',
            'attributes' => [
                'required' => true,
            ],
        ]);

        

        CRUD::addField([
            'name' => 'order_product_type',
            'label' => 'Order Product Type',
            'type' => 'select_from_array',
            'options' => [
                'mirror' => 'Mirror',
                'glass' => 'Glass',
                'lamix' => 'Lamix',
                'glass_pkg' => 'Glass Package',
                'service' => 'Service'
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
                    'allows_null' => false,
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
                        'class' => 'form-group col-md-3 extra extra-qty d-none'
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
                    ],
                    'allows_null' => true,
                    'wrapper' => [
                        'class' => 'form-group col-md-3 extra extra-color d-none'
                    ],
                ],
                [
                    'name' => 'light_type', 
                    'label' => 'Light Type', 
                    'type' => 'select_from_array', 
                    'options' => [
                        'თბილი' => 'თბილი',
                        'ბნელი' => 'ბნელი',
                        'სინათლისმცირებული' => 'სინათლისმცირებული',
                    ], 
                    'wrapper' => ['class' => 'form-group col-md-3 extra extra-light-type d-none']
                ],
               
            ],
            'hint' => 'Add services to this order',
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
                    'label'     => 'Width',
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
                    'label'     => 'Height',
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
        
        // Make order_product_type readonly on edit page
        $this->crud->modifyField('order_product_type', [
            'attributes' => [
                'disabled' => 'disabled',
            ],
            'wrapper' => [
                'class' => 'form-group col-md-12 readonly-field'
            ],
            'hint' => 'Order Product Type cannot be changed after creation',
        ]);
        
        // Populate pieces data for editing
        $this->crud->modifyField('pieces', [
            'value' => $this->crud->getCurrentEntry()->pieces->map(function($piece) {
                return [
                    'width' => $piece->width,
                    'height' => $piece->height,
                    'quantity' => $piece->quantity,
                ];
            })->toArray()
        ]);

        // Populate services data for editing
        $this->crud->modifyField('services', [
            'value' => $this->crud->getCurrentEntry()->services->map(function($service) {
                return [
                    'service_id' => $service->id,
                    'quantity' => $service->pivot->quantity,
                    'description' => $service->pivot->description,
                ];
            })->toArray()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        $this->crud->hasAccessOrFail('create');

        // execute the FormRequest authorization and validation, if one is required
        $request = $this->crud->validateRequest();

        // register any Model Events defined on fields
        $this->crud->registerFieldEvents();

        // insert item in the db
        $item = $this->crud->create($this->crud->getStrippedSaveRequest($request));
        $this->data['entry'] = $this->crud->entry = $item;

        // Handle products relationship
        if ($request->has('products') && is_array($request->products)) {
            $productIds = collect($request->products)
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();
            
            if (!empty($productIds)) {
                $item->products()->sync($productIds);
            }
        }

        // Handle services relationship
        if ($request->has('services') && is_array($request->services)) {
            $servicesData = [];
            foreach ($request->services as $serviceData) {
                if (!empty($serviceData['service_id'])) {
                    $servicesData[$serviceData['service_id']] = [
                        'quantity' => $serviceData['quantity'] ?? 1,
                        'description' => $serviceData['description'] ?? null,
                    ];
                }
            }
            
            if (!empty($servicesData)) {
                $item->services()->sync($servicesData);
            }
        }

        // Handle pieces creation
        if ($request->has('pieces') && is_array($request->pieces)) {
            // Delete existing pieces for this order
            $item->pieces()->delete();
            
            // Create new pieces
            foreach ($request->pieces as $pieceData) {
                if (!empty($pieceData['width']) && !empty($pieceData['height']) && !empty($pieceData['quantity'])) {
                    $item->pieces()->create([
                        'width' => $pieceData['width'],
                        'height' => $pieceData['height'],
                        'quantity' => $pieceData['quantity'],
                        'status' => 'pending',
                    ]);
                }
            }
        }

        // show a success message
        \Alert::success(trans('backpack::crud.insert_success'))->flash();

        // save the redirect choice for next time
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($item->getKey());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update()
    {
        $this->crud->hasAccessOrFail('update');

        // execute the FormRequest authorization and validation, if one is required
        $request = $this->crud->validateRequest();

        // register any Model Events defined on fields
        $this->crud->registerFieldEvents();

        // update the row in the db
        $item = $this->crud->update($request->get($this->crud->model->getKeyName()),
                            $this->crud->getStrippedSaveRequest($request));
        $this->data['entry'] = $this->crud->entry = $item;

        // Handle products relationship
        if ($request->has('products') && is_array($request->products)) {
            $productIds = collect($request->products)
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();
            
            if (!empty($productIds)) {
                $item->products()->sync($productIds);
            }
        }

        // Handle services relationship
        if ($request->has('services') && is_array($request->services)) {
            $servicesData = [];
            foreach ($request->services as $serviceData) {
                if (!empty($serviceData['service_id'])) {
                    $servicesData[$serviceData['service_id']] = [
                        'quantity' => $serviceData['quantity'] ?? 1,
                        'description' => $serviceData['description'] ?? null,
                    ];
                }
            }
            
            if (!empty($servicesData)) {
                $item->services()->sync($servicesData);
            }
        }

        // Handle pieces creation/update
        if ($request->has('pieces') && is_array($request->pieces)) {
            // Delete existing pieces for this order
            $item->pieces()->delete();
            
            // Create new pieces
            foreach ($request->pieces as $pieceData) {
                if (!empty($pieceData['width']) && !empty($pieceData['height']) && !empty($pieceData['quantity'])) {
                    $item->pieces()->create([
                        'width' => $pieceData['width'],
                        'height' => $pieceData['height'],
                        'quantity' => $pieceData['quantity'],
                        'status' => 'pending',
                    ]);
                }
            }
        }

        // show a success message
        \Alert::success(trans('backpack::crud.update_success'))->flash();

        // save the redirect choice for next time
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($item->getKey());
    }

}
