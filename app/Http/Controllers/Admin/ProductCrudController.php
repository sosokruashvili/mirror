<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ProductRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Arr;
use App\Models\Product;
/**
 * Class ProductCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ProductCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Product::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/product');
        CRUD::setEntityNameStrings(__('product.entity'), __('product.entity_plural'));
        
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
            'label' => __('product.id'),
            'type' => 'number',
        ]);

        $this->crud->addColumn([
            'name' => 'title',
            'label' => __('product.title'),
            'type' => 'text',
        ]);

        $this->crud->addColumn([
            'name' => 'product_type',
            'label' => __('product.product_type'),
            'type' => 'text',
        ]);

        $this->crud->addColumn([
            'name' => 'price',
            'label' => __('product.price_usd'),
            'type' => 'number',
            'decimals' => 2,
        ]);

        $this->crud->addColumn([
            'name' => 'price_w',
            'label' => __('product.wholesale_price_usd'),
            'type' => 'number',
            'decimals' => 2,
        ]);

        $this->crud->addColumn([
            'name' => 'offcut',
            'label' => __('product.offcut'),
            'type' => 'number',
            'decimals' => 2,
        ]);

        // Add Filters
        $this->crud->addFilter([
            'name' => 'product_type',
            'type' => 'select2',
            'label' => __('product.product_type')
        ], function() {
            return Arr::only(__('material_type'), ['glass', 'film']);
        }, function($value) {
            $this->crud->addClause('where', 'product_type', $value);
        });

        $this->crud->addFilter([
            'type' => 'range',
            'name' => 'price',
            'label' => __('product.filters.retail_price'),
            'label_from' => 'Min price',
            'label_to' => 'Max price'
        ],
        false,
        function($value) {
            $range = json_decode($value);
            if ($range->from) {
                $this->crud->addClause('where', 'price', '>=', (float) $range->from);
            }
            if ($range->to) {
                $this->crud->addClause('where', 'price', '<=', (float) $range->to);
            }
        });

        $this->crud->addFilter([
            'type' => 'range',
            'name' => 'price_w',
            'label' => __('product.filters.wholesale_price'),
            'label_from' => 'Min price',
            'label_to' => 'Max price'
        ],
        false,
        function($value) {
            $range = json_decode($value);
            if ($range->from) {
                $this->crud->addClause('where', 'price_w', '>=', (float) $range->from);
            }
            if ($range->to) {
                $this->crud->addClause('where', 'price_w', '<=', (float) $range->to);
            }
        });

        $this->crud->addFilter([
            'type' => 'text',
            'name' => 'title',
            'label' => __('product.title')
        ],
        false,
        function($value) {
            $this->crud->addClause('where', 'title', 'LIKE', "%{$value}%");
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
            'label' => __('product.filters.order')
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
        CRUD::setValidation(ProductRequest::class);

        CRUD::addField([
            'name' => 'title',
            'label' => __('product.title'),
            'type' => 'text',
            'attributes' => [
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'product_type',
            'label' => __('product.product_type'),
            'type' => 'select_from_array',
            'options' => __('material_type'),
            'allows_null' => false,
            'default' => 'glass',
        ]);

        CRUD::addField([
            'name' => 'price',
            'label' => __('product.retail_price_field'),
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
                'required' => true,
            ],
            'prefix' => '$',
        ]);

        CRUD::addField([
            'name' => 'price_w',
            'label' => __('product.wholesale_price_field'),
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
            ],
            'prefix' => '$',
            'hint' => __('product.hints.wholesale_price'),
        ]);

        CRUD::addField([
            'name' => 'offcut',
            'label' => __('product.offcut'),
            'type' => 'number',
            'attributes' => [
                'step' => '0.01',
                'min' => '0',
                'max' => '100',
            ],
            'default' => 0,
            'suffix' => '%',
            'hint' => __('product.hints.offcut'),
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

    public function getProductsFiltered($product_type = null)
    {
        switch($product_type) {
            case 'glass':
                $products = Product::where('product_type', 'glass')->get();
                break;
            case 'mirror':
                $products = Product::where('product_type', 'mirror')->get();
                break;
            case 'lamix':
                $products = Product::where('product_type', 'film')->orWhere('product_type', 'glass')->get();
                break;
            case 'glass_pkg':
                $products = Product::where('product_type', 'glass')->orWhere('product_type', 'butyl')->get();
                break;
        }
        return response()->json($products);
    }

    public function getProductPrice($id)
    {
        $product = Product::find($id);
        
        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }
        
        $price = $product->price;
        $price_w = $product->price_w;
        $isCustom = false;
        
        // Check for custom price if client_id is provided
        $clientId = request()->query('client_id');
        if ($clientId) {
            $customPrice = \App\Models\CustomPrice::where('client_id', $clientId)
                ->where('product_id', $id)
                ->first();
            
            if ($customPrice && $customPrice->price_usd) {
                // Custom price overrides default price
                $price = $customPrice->price_usd;
                // For wholesale, use custom price or default wholesale price if custom doesn't specify
                // (assuming custom price applies to both retail and wholesale, or you can add price_w_usd to CustomPrice model)
                $price_w = $customPrice->price_usd;
                $isCustom = true;
            }
        }
        
        return response()->json([
            'price' => $price,
            'price_w' => $price_w,
            'offcut' => (float) ($product->offcut ?? 0),
            'is_custom' => $isCustom,
        ]);
    }
}
