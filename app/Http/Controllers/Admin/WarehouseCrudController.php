<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\WarehouseRequest;
use App\Models\Product;
use App\Services\WarehouseSnapshotService;
use App\Support\SimpleXlsxWriter;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * Resolve which daily snapshot to display and its rows.
     *
     * The table is served from the stored daily warehouse snapshot (generated at
     * 00:00). The date picker (snapshot_date) chooses which day to show, defaulting
     * to the most recent snapshot. Before the first snapshot ever runs, we fall back
     * to a live "as of now" calculation so the page is never empty.
     *
     * The summary_product_id filter narrows the visible rows and stays independent
     * of the CRUD list's own product_id filter below.
     *
     * @return array{rows: \Illuminate\Support\Collection, selected_date: ?string, available_dates: \Illuminate\Support\Collection, is_live: bool}
     */
    protected function resolveSnapshot(): array
    {
        $service = app(WarehouseSnapshotService::class);

        $availableDates = $service->availableSnapshotDates();
        $requested = request('snapshot_date');
        $selectedDate = ($requested && $availableDates->contains($requested))
            ? $requested
            : $availableDates->first();

        if ($selectedDate) {
            $rows = $service->rowsForDate($selectedDate);
            $isLive = false;
        } else {
            // No snapshot has been generated yet — show a live calculation.
            $rows = $service->calculateAsOf(now());
            $isLive = true;
        }

        // This table's own product filter (independent of the CRUD list filter).
        if ($productId = request('summary_product_id')) {
            $rows = $rows->where('id', (int) $productId)->values();
        }

        return [
            'rows' => $rows,
            'selected_date' => $selectedDate,
            'available_dates' => $availableDates,
            'is_live' => $isLive,
        ];
    }

    /**
     * Register the per-product remaining stock list above the table.
     *
     * @return void
     */
    protected function addRemainingStockWidget()
    {
        $snapshot = $this->resolveSnapshot();

        Widget::add([
            'type' => 'view',
            'view' => 'vendor.backpack.crud.widgets.warehouse_remaining',
            'wrapper' => ['class' => 'col-12'],
            'rows' => $snapshot['rows'],
            'products' => Product::query()->orderBy('title')->pluck('title', 'id'),
            'selected_product' => request('summary_product_id'),
            'available_dates' => $snapshot['available_dates'],
            'selected_date' => $snapshot['selected_date'],
            'is_live' => $snapshot['is_live'],
        ])->to('before_content');
    }

    /**
     * Export the per-product remaining stock summary as an .xlsx file.
     *
     * Honours the same snapshot_date and summary_product_id the on-page table is
     * showing, so the download always matches what the user is currently looking at.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportRemainingStock(): StreamedResponse
    {
        $this->crud->hasAccessOrFail('list');

        $snapshot = $this->resolveSnapshot();

        $headings = ['Product', 'Offcut (%)', 'Offcut (m²)', 'In warehouse (m²)', 'Expenses (m²)', 'Remaining (m²)'];

        $rows = $snapshot['rows']->map(function ($row) {
            return [
                $row->title,
                round($row->offcut, 2),
                round($row->offcut_area, 3),
                round($row->warehouse_area, 3),
                round($row->expenses, 3),
                round($row->remaining, 3),
            ];
        })->all();

        $contents = SimpleXlsxWriter::build($headings, $rows);
        $filename = 'warehouse-remaining-stock-' . ($snapshot['selected_date'] ?? now()->format('Y-m-d')) . '.xlsx';

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Manually (re)generate today's warehouse snapshot on demand, instead of
     * waiting for the scheduled 00:00 run. Overwrites today's snapshot if it
     * already exists, then returns to the list showing the fresh values.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function recalculate(WarehouseSnapshotService $service)
    {
        $this->crud->hasAccessOrFail('list');

        $date = now();
        $count = $service->snapshotDailyStock($date);

        \Alert::success("Recalculated warehouse stock for {$count} product(s) on {$date->format('Y-m-d')}.")->flash();

        $params = ['snapshot_date' => $date->toDateString()];
        if (request()->filled('summary_product_id')) {
            $params['summary_product_id'] = request('summary_product_id');
        }

        return redirect()->route('warehouse.index', $params);
    }
}
