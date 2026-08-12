<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ServiceRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Arr;

/**
 * Class ServiceCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ServiceCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Service::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/service');
        CRUD::setEntityNameStrings(__('service.entity'), __('service.entity_plural'));
        
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
            'label' => __('service.id'),
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'title',
            'label' => __('service.title'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'shortname',
            'label' => __('service.short_name'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'slug',
            'label' => __('service.slug'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'stage',
            'label' => __('service.stage'),
            'type' => 'relationship',
            'attribute' => 'title',
        ]);

        CRUD::addColumn([
            'name' => 'description',
            'label' => __('service.description'),
            'type' => 'text',
            'limit' => 100,
        ]);

        CRUD::addColumn([
            'name' => 'unit',
            'label' => __('service.unit'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'price',
            'label' => __('service.price_usd'),
            'type' => 'number',
            'decimals' => 2,
        ]);

        CRUD::addColumn([
            'name' => 'price_gel',
            'label' => __('service.price_gel'),
            'type' => 'number',
            'decimals' => 2,
        ]);

        CRUD::addColumn([
            'name' => 'cutloss',
            'label' => __('service.cutting_loss'),
            'type' => 'number',
            'decimals' => 0,
        ]);

        CRUD::addColumn([
            'name'  => 'extra_field_names',
            'label' => __('service.extra_field_names'),
            'type'  => 'custom_html',
            'value' => function ($entry) {
                if (is_array($entry->extra_field_names)) {
                    return implode(', ', $entry->extra_field_names) . '<br>';
                }
            },
        ]);

        $this->addStandardFilters();

        // Filter services by production stage.
        CRUD::addFilter([
            'type'  => 'select2',
            'name'  => 'stage_id',
            'label' => __('service.stage'),
        ], function () {
            return \App\Models\Stage::orderBy('position')->orderBy('id')->pluck('title', 'id')->toArray();
        }, function ($value) {
            CRUD::addClause('where', 'stage_id', $value);
        });

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
            'label' => __('service.filters.order')
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
            'label' => __('service.title'),
            'type' => 'text',
            'attributes' => [
                'required' => true,
            ],
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'shortname',
            'label' => __('service.short_name'),
            'type' => 'text',
            'hint' => __('service.hints.short_name'),
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'slug',
            'label' => __('service.slug'),
            'type' => 'slug',
            'source' => 'title',
            'attributes' => [
                'required' => true,
            ],
            'hint' => __('service.hints.slug'),
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'stage',
            'label' => __('service.stage'),
            'type' => 'relationship',
            'attribute' => 'title',
            'placeholder' => __('service.placeholders.stage'),
            'allows_null' => false,
            'attributes' => [
                'required' => true,
            ],
            'hint' => __('service.hints.stage'),
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'description',
            'label' => __('service.description'),
            'type' => 'textarea',
            'attributes' => [
                'rows' => 4,
            ],
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'unit',
            'label' => __('service.unit'),
            'type' => 'text',
            'attributes' => [
                'required' => true,
                'placeholder' => __('service.placeholders.unit'),
            ],
            'hint' => __('service.hints.unit'),
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'price',
            'label' => __('service.price_usd_field'),
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
            ],
            'prefix' => '$',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'price_gel',
            'label' => __('service.price_gel_field'),
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
            ],
            'prefix' => '₾',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name' => 'cutloss',
            'label' => __('service.cutting_loss'),
            'type' => 'number',
            'attributes' => [
                'step' => '1',
                'min' => '0',
            ],
            'default' => 0,
            'hint' => __('service.hints.cutting_loss'),
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);

        // select2_from_array with value support for updateOperation
        CRUD::field([
            'name'        => 'extra_field_names',
            'label'       => __('service.extra_field_names'),
            'type'        => 'select2_from_array',
            // Labels come from the service_pivot lang group (the keys are the
            // pivot column names); listed explicitly to keep the display order.
            'options'     => Arr::only(__('service_pivot'), [
                'antifog_type', 'quantity', 'perimeter', 'color', 'light_type',
                'foam_length', 'tape_length', 'area', 'length_cm', 'sensor_quantity1',
                'sensor_type', 'distance', 'description', 'price_gel', 'piece_id',
                'calculate_price_btn',
            ]),
            'allows_null' => true,
            'default'     => [],
            'allows_multiple' => true,
            'wrapper' => ['class' => 'form-group col-md-6'],
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
            'label' => __('service.id'),
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'title',
            'label' => __('service.title'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'shortname',
            'label' => __('service.short_name'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'slug',
            'label' => __('service.slug'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'stage',
            'label' => __('service.stage'),
            'type' => 'relationship',
            'attribute' => 'title',
        ]);

        CRUD::addColumn([
            'name' => 'description',
            'label' => __('service.description'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'unit',
            'label' => __('service.unit'),
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'price',
            'label' => __('service.price_usd'),
            'type' => 'number',
            'decimals' => 2,
        ]);

        CRUD::addColumn([
            'name' => 'price_gel',
            'label' => __('service.price_gel'),
            'type' => 'number',
            'decimals' => 2,
        ]);

        CRUD::addColumn([
            'name' => 'cutloss',
            'label' => __('service.cutting_loss'),
            'type' => 'number',
            'decimals' => 0,
        ]);

        CRUD::addColumn([
            'name'  => 'extra_field_names',
            'label' => __('service.extra_field_names'),
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
            'label' => __('service.title'),
        ],
        false,
        function ($value) {
            CRUD::addClause('where', 'title', 'LIKE', '%' . $value . '%');
        });

        CRUD::addFilter([
            'type'  => 'select2',
            'name'  => 'unit',
            'label' => __('service.unit'),
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
            'label' => __('service.price_usd_field'),
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
            'label' => __('service.price_gel_field'),
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

    /**
     * Generate a URL-friendly slug from a title (supports Georgian transliteration).
     */
    public function generateSlug(\Illuminate\Http\Request $request)
    {
        $title = (string) $request->query('title', '');

        return response()->json([
            'slug' => \Illuminate\Support\Str::slug($title),
        ]);
    }
}

