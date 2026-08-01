<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\WarehouseCorrectionRequest;
use App\Models\WarehouseCorrection;
use App\Services\WarehouseSnapshotService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Carbon\Carbon;

/**
 * Class WarehouseCorrectionCrudController
 *
 * Hand-entered adjustments to remaining warehouse stock — stocktake differences,
 * breakage, material found or returned. Every correction is a permanent ledger
 * row carrying its reason and author, and it feeds straight into the warehouse
 * snapshot calculation.
 *
 * Saving, editing or deleting a correction rebuilds the stored snapshots from its
 * effective date onward, so the Stock page reflects it immediately instead of
 * waiting for the nightly run.
 *
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class WarehouseCorrectionCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation { destroy as traitDestroy; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(WarehouseCorrection::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/warehouse-correction');
        CRUD::setEntityNameStrings('correction', 'corrections');

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
        $this->crud->orderBy('effective_date', 'desc')->orderBy('id', 'desc');
        $this->crud->with(['product', 'user']);

        CRUD::addColumn([
            'name' => 'id',
            'label' => 'ID',
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'product_id',
            'label' => 'Product',
            'type' => 'select',
            'entity' => 'product',
            'attribute' => 'title',
            'model' => 'App\Models\Product',
        ]);

        CRUD::addColumn([
            'name' => 'area',
            'label' => 'Correction (m²)',
            'type' => 'custom_html',
            'value' => function ($entry) {
                $area = (float) $entry->area;
                $class = $area < 0 ? 'text-danger' : 'text-success';
                $text = ($area > 0 ? '+' : '') . number_format($area, 3);

                return '<span class="fw-bold ' . $class . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'effective_date',
            'label' => 'Effective From',
            'type' => 'date',
        ]);

        CRUD::addColumn([
            'name' => 'reason',
            'label' => 'Reason',
            'type' => 'text',
            'limit' => 80,
        ]);

        CRUD::addColumn([
            'name' => 'user_id',
            'label' => 'Entered By',
            'type' => 'select',
            'entity' => 'user',
            'attribute' => 'name',
            'model' => 'App\Models\User',
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'label' => 'Entered At',
            'type' => 'datetime',
        ]);

        CRUD::addFilter([
            'name' => 'product_id',
            'type' => 'select2',
            'label' => 'Product',
        ], function () {
            return \App\Models\Product::orderBy('title')->pluck('title', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'product_id', $value);
        });

        CRUD::addFilter([
            'name' => 'effective_date',
            'type' => 'date_range',
            'label' => 'Effective Date Range',
        ], false, function ($value) {
            $dates = json_decode($value, true);
            if (!empty($dates['from'])) {
                $this->crud->addClause('where', 'effective_date', '>=', date('Y-m-d', strtotime($dates['from'])));
            }
            if (!empty($dates['to'])) {
                $this->crud->addClause('where', 'effective_date', '<=', date('Y-m-d', strtotime($dates['to'])));
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
        CRUD::setValidation(WarehouseCorrectionRequest::class);

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
            'name' => 'area',
            'label' => 'Correction (m²)',
            'type' => 'number',
            'hint' => 'Signed adjustment to remaining stock. Use a <b>negative</b> value to write stock off (e.g. -12.5 for breakage or a stocktake shortfall) and a <b>positive</b> value to add stock back.',
            'attributes' => [
                'step' => '0.001',
                'required' => true,
            ],
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'effective_date',
            'label' => 'Effective From',
            'type' => 'date',
            'default' => now()->toDateString(),
            'hint' => 'The correction counts from this day onward. Snapshots before it are left untouched.',
            'attributes' => [
                'required' => true,
            ],
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'reason',
            'label' => 'Reason',
            'type' => 'textarea',
            'hint' => 'Required — this is the record of why the stock was adjusted.',
            'attributes' => [
                'required' => true,
                'rows' => 3,
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
     * Rebuild the snapshots the new correction affects. The author is stamped by
     * the model itself (see WarehouseCorrection::booted).
     */
    public function store()
    {
        $response = $this->traitStore();

        $this->rebuildFrom($this->currentEffectiveDate());

        return $response;
    }

    /**
     * Rebuild from the earlier of the old and new effective dates, so moving a
     * correction backwards or forwards in time corrects both ranges.
     */
    public function update()
    {
        $previous = $this->currentEffectiveDate();

        $response = $this->traitUpdate();

        $this->rebuildFrom($this->earliest($previous, $this->currentEffectiveDate()));

        return $response;
    }

    /**
     * Removing a correction also rolls it back out of the stored snapshots.
     */
    public function destroy($id)
    {
        $effectiveDate = $this->crud->getModel()->find($id)?->effective_date;

        $response = $this->traitDestroy($id);

        $this->rebuildFrom($effectiveDate);

        return $response;
    }

    /**
     * Effective date of the entry this request is acting on, or null.
     *
     * getCurrentEntry() returns `false` rather than null when there is no id to
     * resolve, so the result is narrowed to a model before reading from it.
     */
    protected function currentEffectiveDate()
    {
        $entry = $this->crud->getCurrentEntry();

        return $entry instanceof WarehouseCorrection ? $entry->effective_date : null;
    }

    /**
     * Replay the stored snapshots from $from onward so the correction shows up on
     * the Stock page straight away. Scoped to the affected range rather than the
     * whole history, since a correction cannot change any earlier day.
     *
     * @return void
     */
    protected function rebuildFrom($from): void
    {
        app(WarehouseSnapshotService::class)
            ->rebuildStoredSnapshots($from ? Carbon::parse($from)->startOfDay() : null);
    }

    /**
     * The earlier of two nullable dates.
     */
    protected function earliest($a, $b)
    {
        if (! $a) {
            return $b;
        }
        if (! $b) {
            return $a;
        }

        return Carbon::parse($a)->lt(Carbon::parse($b)) ? $a : $b;
    }
}
