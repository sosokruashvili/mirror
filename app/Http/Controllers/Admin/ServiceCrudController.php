<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ServiceRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class ServiceCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ServiceCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Service::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/service');
        CRUD::setEntityNameStrings('service', 'services');
        
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
        $this->crud->orderBy('id', 'asc');
        
        $this->crud->addColumn([
            'name' => 'id',
            'label' => 'ID',
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'slug',
            'label' => 'Slug',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'text',
            'limit' => 100,
        ]);

        CRUD::addColumn([
            'name' => 'unit',
            'label' => 'Unit',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'price',
            'label' => 'Price (USD)',
            'type' => 'number',
            'decimals' => 2,
            'prefix' => '$',
        ]);

        CRUD::addColumn([
            'name' => 'price_gel',
            'label' => 'Price (GEL)',
            'type' => 'number',
            'decimals' => 2,
            'prefix' => '₾',
        ]);

        CRUD::addColumn([
            'name'  => 'extra_field_names',
            'label' => 'Extra Field Names',
            'type'  => 'custom_html',
            'value' => function ($entry) {
                if (is_array($entry->extra_field_names)) {
                    return implode(', ', $entry->extra_field_names) . '<br>';
                }
            },
        ]);

        $this->addStandardFilters();

        // Add filter for order_id if present in URL
        if (request()->has('order_id')) {
            $orderId = request()->get('order_id');
            $this->crud->addClause('whereHas', 'orders', function($query) use ($orderId) {
                $query->where('orders.id', $orderId);
            });
        }

        // Add filter for order_id
        $this->crud->addFilter([
            'name' => 'order_id',
            'type' => 'select2',
            'label' => 'Order'
        ], function() {
            return \App\Models\Order::all()->pluck('id', 'id')->toArray();
        }, function($value) {
            $this->crud->addClause('whereHas', 'orders', function($query) use ($value) {
                $query->where('orders.id', $value);
            });
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
        CRUD::setValidation(ServiceRequest::class);

        CRUD::addField([
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
            'attributes' => [
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'slug',
            'label' => 'Slug',
            'type' => 'text',
            'hint' => 'URL-friendly version of the title',
        ]);

        CRUD::addField([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'textarea',
            'attributes' => [
                'rows' => 4,
            ],
        ]);

        CRUD::addField([
            'name' => 'unit',
            'label' => 'Unit',
            'type' => 'text',
            'attributes' => [
                'required' => true,
                'placeholder' => 'e.g., hour, piece, sq ft',
            ],
            'hint' => 'Unit of measurement for this service',
        ]);

        CRUD::addField([
            'name' => 'price',
            'label' => 'Price (USD)',
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
            ],
            'prefix' => '$',
        ]);

        CRUD::addField([
            'name' => 'price_gel',
            'label' => 'Price (GEL)',
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
            ],
            'prefix' => '₾',
        ]);

        // select2_from_array with value support for updateOperation
        CRUD::field([
            'name'        => 'extra_field_names',
            'label'       => "Extra Field Names",
            'type'        => 'select2_from_array',
            'options'     => [
                'antifog_type' => 'Anti Fog Type',
                'quantity' => 'Quantity',
                'perimeter' => 'Perimeter',
                'color' => 'Color',
                'light_type' => 'Light Type',
                'foam_length' => 'Foam Length',
                'tape_length' => 'Tape Length',
                'area' => 'Area',
                'length_cm' => 'Length (cm)',
                'sensor_quantity1' => 'Sensor Quantity',
                'sensor_type' => 'Sensor Type',
                'distance' => 'Distance',
                'description' => 'Description',
                'price_gel' => 'Price (GEL)',
            ],
            'allows_null' => true,
            'default'     => [],
            'allows_multiple' => true,
            // For update operation, try to load the previously selected value
            'value' => function () {
                $entry = backpack_crud()->getCurrentEntry();
                if ($entry) {
                    $extraFieldNames = $entry->extra_field_names ?? [];
                    if (is_string($extraFieldNames)) {
                        $extraFieldNames = json_decode($extraFieldNames, true) ?? [];
                    }
                    if (!is_array($extraFieldNames)) {
                        $extraFieldNames = [];
                    }
                    return $extraFieldNames;
                }
                return [];
            }
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
        
        $entry = $this->crud->getCurrentEntry();
        
        // Populate extra_field_names for editing
        if ($entry) {
            $extraFieldNames = $entry->extra_field_names ?? [];
            if (is_string($extraFieldNames)) {
                $extraFieldNames = json_decode($extraFieldNames, true) ?? [];
            }
            if (!is_array($extraFieldNames)) {
                $extraFieldNames = [];
            }
            
            $this->crud->modifyField('extra_field_names', [
                'value' => $extraFieldNames,
            ]);
        }
    }

    /**
     * Define what happens when the Show operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-show
     * @return void
     */
    protected function setupShowOperation()
    {
        CRUD::addColumn([
            'name' => 'id',
            'label' => 'ID',
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'slug',
            'label' => 'Slug',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'description',
            'label' => 'Description',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'unit',
            'label' => 'Unit',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'price',
            'label' => 'Price (USD)',
            'type' => 'number',
            'decimals' => 2,
            'prefix' => '$',
        ]);

        CRUD::addColumn([
            'name' => 'price_gel',
            'label' => 'Price (GEL)',
            'type' => 'number',
            'decimals' => 2,
            'prefix' => '₾',
        ]);

        CRUD::addColumn([
            'name'  => 'extra_field_names',
            'label' => 'Extra Field Names',
            'type'  => 'custom_html',
            'value' => function ($entry) {
                if (is_array($entry->extra_field_names)) {
                    return implode(', ', $entry->extra_field_names);
                }
                return '-';
            },
        ]);
    }

    /**
     * Register reusable list filters.
     *
     * @return void
     */
    protected function addStandardFilters(): void
    {
        CRUD::addFilter([
            'type'  => 'text',
            'name'  => 'title',
            'label' => 'Title',
        ],
        false,
        function ($value) {
            CRUD::addClause('where', 'title', 'LIKE', '%' . $value . '%');
        });

        CRUD::addFilter([
            'type'  => 'select2',
            'name'  => 'unit',
            'label' => 'Unit',
        ],
        function () {
            return \App\Models\Service::query()
                ->whereNotNull('unit')
                ->where('unit', '<>', '')
                ->distinct()
                ->pluck('unit', 'unit')
                ->toArray();
        },
        function ($value) {
            CRUD::addClause('where', 'unit', $value);
        });

        CRUD::addFilter([
            'type'  => 'range',
            'name'  => 'price',
            'label' => 'Price (USD)',
        ],
        false,
        function ($value) {
            $range = json_decode($value, true);

            if (is_array($range)) {
                if (isset($range['from']) && $range['from'] !== '') {
                    CRUD::addClause('where', 'price', '>=', $range['from']);
                }

                if (isset($range['to']) && $range['to'] !== '') {
                    CRUD::addClause('where', 'price', '<=', $range['to']);
                }
            }
        });

        CRUD::addFilter([
            'type'  => 'range',
            'name'  => 'price_gel',
            'label' => 'Price (GEL)',
        ],
        false,
        function ($value) {
            $range = json_decode($value, true);

            if (is_array($range)) {
                if (isset($range['from']) && $range['from'] !== '') {
                    CRUD::addClause('where', 'price_gel', '>=', $range['from']);
                }

                if (isset($range['to']) && $range['to'] !== '') {
                    CRUD::addClause('where', 'price_gel', '<=', $range['to']);
                }
            }
        });
    }

    /**
     * Get extra field names for a service.
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getExtraFields($id)
    {
        $service = \App\Models\Service::find($id);
        
        if (!$service) {
            return response()->json(['error' => 'Service not found'], 404);
        }
        
        $extraFieldNames = $service->extra_field_names ?? [];
        if (is_string($extraFieldNames)) {
            $extraFieldNames = json_decode($extraFieldNames, true) ?? [];
        }
        if (!is_array($extraFieldNames)) {
            $extraFieldNames = [];
        }
        
        return response()->json([
            'extra_field_names' => $extraFieldNames
        ]);
    }
}
