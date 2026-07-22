<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;

/**
 * Class WarehouseExpenseCrudController
 *
 * Read-only report of orders and the warehouse material (m²) each one has
 * consumed, so warehouse staff can see where expenses are coming from.
 *
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class WarehouseExpenseCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(Order::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/warehouse-expense');
        CRUD::setEntityNameStrings('order expense', 'order expenses');

        // Disable create, update, delete, show operations (read-only report)
        $this->crud->denyAccess(['create', 'update', 'delete', 'show']);

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
        $this->crud->orderBy('created_at', 'desc');

        $this->addWarehouseExpenseStatsWidget();

        CRUD::addColumn([
            'name' => 'id',
            'label' => 'Order ID',
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
                $titles = $entry->products->pluck('title');
                if ($titles->isEmpty()) {
                    return '<span class="text-muted">-</span>';
                }
                return htmlspecialchars($titles->implode(', '), ENT_QUOTES, 'UTF-8');
            }
        ]);

        CRUD::addColumn([
            'name' => 'expenses',
            'label' => 'Expenses (m²)',
            'type' => 'number',
            'decimals' => 2,
            'suffix' => ' m²',
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'label' => 'Created At',
            'type' => 'datetime',
        ]);

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
                'ready' => 'Ready',
                'finished' => 'Finished',
            ];
        },
        function ($value) {
            CRUD::addClause('where', 'status', $value);
        });

        // Date range filter
        CRUD::addFilter([
            'name' => 'created_at',
            'type' => 'date_range',
            'label' => 'Order Date Range',
        ],
        false,
        function ($value) {
            $dates = json_decode($value, true);
            if (!empty($dates['from'])) {
                // Filter may already include a time; normalize to start of day
                CRUD::addClause('where', 'created_at', '>=', date('Y-m-d', strtotime($dates['from'])) . ' 00:00:00');
            }
            if (!empty($dates['to'])) {
                CRUD::addClause('where', 'created_at', '<=', date('Y-m-d', strtotime($dates['to'])) . ' 23:59:59');
            }
        });
    }

    /**
     * Apply the list filters (client, product type, status, date range) to a
     * query so the summary widget totals match exactly what the filtered table
     * shows.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyWarehouseExpenseFilters($query)
    {
        if (request()->filled('client_id')) {
            $query->where('client_id', request()->get('client_id'));
        }

        if (request()->filled('product_type')) {
            $query->where('product_type', request()->get('product_type'));
        }

        if (request()->filled('status')) {
            $query->where('status', request()->get('status'));
        }

        if (request()->filled('created_at')) {
            $dates = json_decode(request()->get('created_at'), true);
            if (is_array($dates)) {
                if (!empty($dates['from'])) {
                    // Filter may already include a time; normalize to start of day
                    $query->where('created_at', '>=', date('Y-m-d', strtotime($dates['from'])) . ' 00:00:00');
                }
                if (!empty($dates['to'])) {
                    $query->where('created_at', '<=', date('Y-m-d', strtotime($dates['to'])) . ' 23:59:59');
                }
            }
        }

        return $query;
    }

    /**
     * Compute the filter-aware summary totals.
     *
     * @return array
     */
    protected function calculateWarehouseExpenseStats(): array
    {
        $query = $this->applyWarehouseExpenseFilters(Order::query());

        return [
            'ordersCount' => (clone $query)->count(),
            'totalExpenses' => (float) (clone $query)->sum('expenses'),
        ];
    }

    /**
     * Add a widget summarizing total warehouse expenses for the current filters.
     *
     * @return void
     */
    protected function addWarehouseExpenseStatsWidget()
    {
        Widget::add(array_merge([
            'type' => 'view',
            'view' => 'vendor.backpack.crud.widgets.warehouse_expense_stats',
            'wrapper' => ['class' => 'col-12'],
        ], $this->calculateWarehouseExpenseStats()))->to('before_content');
    }

    /**
     * Return the filter-aware summary totals as JSON (for live widget updates).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWarehouseExpenseStats(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->calculateWarehouseExpenseStats());
    }
}
