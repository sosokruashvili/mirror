<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\WarehouseRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Warehouse;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Class WarehouseCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class WarehouseCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Warehouse::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/warehouse');
        CRUD::setEntityNameStrings('warehouse', 'warehouses');
        
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
        // Set custom button label for list page
        CRUD::setEntityNameStrings('warehouse item', 'warehouses');
        
        $this->crud->orderBy('id', 'desc');

        $this->addRemainingStockWidget();

        $this->crud->addColumn([
            'name' => 'id',
            'label' => 'ID',
            'type' => 'number',
        ]);

        $this->crud->addColumn([
            'name' => 'product_id',
            'label' => 'Product',
            'type' => 'select',
            'entity' => 'product',
            'attribute' => 'title',
            'model' => 'App\Models\Product',
        ]);

        $this->crud->addColumn([
            'name' => 'quantity',
            'label' => 'Quantity of lists',
            'type' => 'number',
        ]);

        $this->crud->addColumn([
            'name' => 'area',
            'label' => 'Area (m²)',
            'type' => 'number',
            'decimals' => 3,
        ]);

        $this->crud->addColumn([
            'name' => 'created_at',
            'label' => 'Created At',
            'type' => 'datetime',
        ]);

        // Add Filters
        $this->crud->addFilter([
            'name' => 'product_id',
            'type' => 'select2',
            'label' => 'Product'
        ], function() {
            return \App\Models\Product::all()->pluck('title', 'id')->toArray();
        }, function($value) {
            $this->crud->addClause('where', 'product_id', $value);
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
        CRUD::setValidation(WarehouseRequest::class);
        
        // Set custom button label
        CRUD::setEntityNameStrings('warehouse item', 'warehouses');

        CRUD::addField([
            'name' => 'product_id',
            'label' => 'Product',
            'type' => 'select',
            'entity' => 'product',
            'model' => 'App\Models\Product',
            'attribute' => 'title',
            'options' => (function ($query) {
                return $query->orderBy('title', 'ASC')->get();
            }),
            'attributes' => [
                'required' => true,
            ],
        ]);

        CRUD::addField([
            'name' => 'quantity',
            'label' => 'Quantity of lists',
            'type' => 'number',
            'default' => 0,
            'attributes' => [
                'step' => '1',
                'min' => '0',
            ],
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'area',
            'label' => 'Area (m²)',
            'type' => 'number',
            'default' => 0,
            'attributes' => [
                'step' => '0.001',
                'min' => '0',
            ],
            'wrapper' => [
                'class' => 'form-group col-md-6',
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
        $this->setupCreateOperation();
    }

    /**
     * Build the per-product remaining stock summary.
     *
     * For each product, remaining area (m²) = total warehouse area
     * minus the total expenses (m²) of every order that contains the product.
     * Quantity of lists is informational only and is not used in the math.
     *
     * Narrowed by this table's own product filter, which uses summary_product_id
     * so it stays independent of the CRUD list's product_id filter below.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getRemainingStock()
    {
        // Total warehouse area per product.
        $warehouseAreas = Warehouse::query()
            ->select('product_id', DB::raw('SUM(area) as total_area'))
            ->groupBy('product_id')
            ->pluck('total_area', 'product_id');

        // Total expenses per product, counting each order once even if a
        // product appears multiple times on the same order.
        $orderExpenses = Order::query()->pluck('expenses', 'id');

        $expensesByProduct = [];
        DB::table('order_product')
            ->select('order_id', 'product_id')
            ->distinct()
            ->get()
            ->each(function ($row) use (&$expensesByProduct, $orderExpenses) {
                $expensesByProduct[$row->product_id] =
                    ($expensesByProduct[$row->product_id] ?? 0) + (float) ($orderExpenses[$row->order_id] ?? 0);
            });

        return Product::query()
            ->when(request('summary_product_id'), function ($query, $productId) {
                $query->where('id', $productId);
            })
            ->orderBy('title')
            ->get()
            ->map(function (Product $product) use ($warehouseAreas, $expensesByProduct) {
                $warehouseArea = (float) ($warehouseAreas[$product->id] ?? 0);
                $expenses = (float) ($expensesByProduct[$product->id] ?? 0);

                return (object) [
                    'id' => $product->id,
                    'title' => $product->title,
                    'warehouse_area' => $warehouseArea,
                    'expenses' => $expenses,
                    'remaining' => $warehouseArea - $expenses,
                ];
            });
    }

    /**
     * Register the per-product remaining stock list above the table.
     *
     * @return void
     */
    protected function addRemainingStockWidget()
    {
        Widget::add([
            'type' => 'view',
            'view' => 'vendor.backpack.crud.widgets.warehouse_remaining',
            'wrapper' => ['class' => 'col-12'],
            'rows' => $this->getRemainingStock(),
            'products' => Product::query()->orderBy('title')->pluck('title', 'id'),
            'selected_product' => request('summary_product_id'),
        ])->to('before_content');
    }
}
