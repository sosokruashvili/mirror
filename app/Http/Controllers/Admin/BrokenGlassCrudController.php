<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\BrokenGlassRequest;
use App\Models\BrokenGlass;
use App\Models\Client;
use App\Models\Machine;
use App\Models\User;
use App\Services\BrokenGlassConfirmation;
use App\Support\BrokenGlass\BreakDetails;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use RuntimeException;

/**
 * The Broken Glasses page: one row per break, with the details the team entered
 * (usable m², machine, stage found) and the production lead's confirmation.
 *
 * - Everyone with `broken-glass.list` sees the list, its totals and the export.
 * - `broken-glass.confirm` adds Edit (pending records only) and Confirm. Confirming
 *   returns the usable m² to stock (App\Services\BrokenGlassConfirmation) and
 *   locks the record.
 * - `broken-glass.unconfirm` (admins by default) undoes a confirmation.
 *
 * Breaks recorded before the dialog had these fields have no machine; they show as
 * "ძველი" (legacy) and cannot be edited or confirmed.
 *
 * There is deliberately no expandable details row and the table is not responsive:
 * a collapsed column does not reach the Excel export, which is the whole point of
 * this page. Exports take every filtered row, not just the visible page
 * (list.exportAllRows in resources/views/vendor/backpack/crud/inc/export_buttons.blade.php).
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class BrokenGlassCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation { search as backpackListSearch; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;

    public function setup()
    {
        CRUD::setModel(BrokenGlass::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/broken-glass');
        CRUD::setEntityNameStrings(__('broken_glass.entity'), __('broken_glass.entity_plural'));

        $this->crud->denyAccess(['create', 'delete', 'show']);

        // Editing is part of checking a record before confirming it.
        $user = backpack_user();
        $this->crud->setAccessCondition('update', function ($entry) use ($user) {
            if (! $user || ! $user->can('broken-glass.confirm')) {
                return false;
            }

            return $entry === null || $entry->isPending();
        });

        $this->crud->enableExportButtons();
        $this->crud->set('list.exportAllRows', true);
        $this->crud->set('list.exportPdfGeorgian', true);
        // 15 columns on a landscape A4 page.
        $this->crud->set('list.exportPdfFontSize', 7);
    }

    /**
     * POST broken-glass/{id}/confirm and broken-glass/{id}/unconfirm.
     */
    protected function setupConfirmationRoutes($segment, $routeName, $controller)
    {
        Route::post($segment . '/{id}/confirm', [
            'as' => $routeName . '.confirm',
            'uses' => $controller . '@confirm',
            'operation' => 'brokenGlassConfirmation',
        ]);

        Route::post($segment . '/{id}/unconfirm', [
            'as' => $routeName . '.unconfirm',
            'uses' => $controller . '@unconfirm',
            'operation' => 'brokenGlassConfirmation',
        ]);
    }

    public function confirm($id)
    {
        $this->crud->hasAccessOrFail('list');
        $user = backpack_user();
        abort_unless($user?->can('broken-glass.confirm'), 403);

        try {
            app(BrokenGlassConfirmation::class)->confirm(BrokenGlass::findOrFail($id), $user);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => __('broken_glass.confirm_dialog.done')]);
    }

    public function unconfirm($id)
    {
        $this->crud->hasAccessOrFail('list');
        abort_unless(backpack_user()?->can('broken-glass.unconfirm'), 403);

        try {
            app(BrokenGlassConfirmation::class)->unconfirm(BrokenGlass::findOrFail($id));
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => __('broken_glass.unconfirm_dialog.done')]);
    }

    protected function setupListOperation()
    {
        $this->crud->with(['order.products', 'piece', 'machine', 'detectedStage']);
        $this->crud->orderBy('created_at', 'desc');
        $this->crud->orderBy('id', 'desc');

        // Collapsed columns would not reach the export; scroll sideways instead.
        $this->crud->disableResponsiveTable();

        $this->crud->addButtonFromView('line', 'broken_glass_confirm', 'broken_glass_confirm', 'end');
        $this->crud->addButtonFromView('line', 'broken_glass_unconfirm', 'broken_glass_unconfirm', 'end');

        Widget::add([
            'type' => 'view',
            'view' => 'admin.broken-glass.list-extras',
        ])->to('before_content');

        $this->setupColumns();
        $this->setupFilters();
    }

    /**
     * ListOperation::search() plus `totals` for the filtered and searched rows,
     * which the summary above the table reads (admin/broken-glass/list-extras).
     */
    public function search()
    {
        $response = $this->backpackListSearch();

        if (! is_array($response)) {
            return $response; // e.g. Backpack's "Unknown page length." error
        }

        // The query still carries the filters and the search term; drop the page
        // window and ordering to sum every matching row.
        $base = (clone $this->crud->query)->toBase();
        $base->limit = null;
        $base->offset = null;
        $base->orders = null;
        $base->columns = null;

        $totals = $base->selectRaw('
            COUNT(*) AS entries,
            COALESCE(SUM(damaged_area), 0) AS damaged,
            COALESCE(SUM(usable_area), 0) AS usable,
            COALESCE(SUM(GREATEST(damaged_area - usable_area, 0)), 0) AS loss
        ')->first();

        $response['totals'] = [
            'entries' => (int) ($totals->entries ?? 0),
            'damaged' => round((float) ($totals->damaged ?? 0), 3),
            'usable' => round((float) ($totals->usable ?? 0), 3),
            'loss' => round((float) ($totals->loss ?? 0), 3),
        ];

        return $response;
    }

    protected function setupColumns(): void
    {
        CRUD::addColumn([
            'name' => 'created_at',
            'label' => __('broken_glass.date'),
            'type' => 'datetime',
            'format' => 'YYYY-MM-DD HH:mm',
            'searchLogic' => false,
        ]);

        CRUD::addColumn([
            'name' => 'order_id',
            'label' => __('broken_glass.order_id'),
            'type' => 'custom_html',
            'escaped' => false,
            'searchLogic' => function ($query, $column, $searchTerm) {
                if (ctype_digit(trim((string) $searchTerm))) {
                    $query->orWhere('order_id', (int) $searchTerm);
                }
            },
            'value' => fn ($entry) => $this->orderLink($entry),
        ]);

        CRUD::addColumn([
            'name' => 'piece_id',
            'label' => __('broken_glass.piece_id'),
            'type' => 'custom_html',
            'escaped' => false,
            'searchLogic' => function ($query, $column, $searchTerm) {
                if (ctype_digit(trim((string) $searchTerm))) {
                    $query->orWhere('piece_id', (int) $searchTerm);
                }
            },
            'value' => fn ($entry) => $this->pieceLink($entry),
        ]);

        CRUD::addColumn([
            'name' => 'client_name',
            'label' => __('broken_glass.client'),
            'type' => 'text',
            'limit' => 200,
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('client_name', 'ilike', '%' . $searchTerm . '%');
            },
        ]);

        CRUD::addColumn([
            'name' => 'glass_type',
            'label' => __('broken_glass.glass_type'),
            'type' => 'text',
            'limit' => 200,
            'orderable' => false,
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('order.products', function ($q) use ($searchTerm) {
                    $q->where('title', 'ilike', '%' . $searchTerm . '%');
                });
            },
            'value' => fn ($entry) => $entry->order ? $entry->order->products->pluck('title')->implode(' x ') : '',
        ]);

        CRUD::addColumn([
            'name' => 'size',
            'label' => __('broken_glass.size'),
            'type' => 'text',
            'orderable' => false,
            'searchLogic' => false,
            'value' => fn ($entry) => $this->sizeLabel($entry),
        ]);

        CRUD::addColumn([
            'name' => 'damaged_area',
            'label' => __('broken_glass.damaged_area'),
            'type' => 'text',
            'searchLogic' => false,
            'value' => fn ($entry) => $this->area($entry->damaged_area),
        ]);

        CRUD::addColumn([
            'name' => 'usable_area',
            'label' => __('broken_glass.usable_area'),
            'type' => 'text',
            'searchLogic' => false,
            'value' => fn ($entry) => $this->area($entry->usable_area),
        ]);

        CRUD::addColumn([
            'name' => 'loss_area',
            'label' => __('broken_glass.loss_area'),
            'type' => 'text',
            'searchLogic' => false,
            'orderLogic' => fn ($query, $column, $direction) => $query->orderByRaw(
                'GREATEST(damaged_area - usable_area, 0) ' . ($direction === 'asc' ? 'ASC' : 'DESC') . ' NULLS LAST'
            ),
            'value' => fn ($entry) => $this->area($entry->loss_area),
        ]);

        CRUD::addColumn([
            'name' => 'machine_id',
            'label' => __('broken_glass.machine'),
            'type' => 'text',
            'limit' => 100,
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('machine', fn ($q) => $q->where('name', 'ilike', '%' . $searchTerm . '%'));
            },
            'value' => fn ($entry) => $entry->machine?->name ?? '—',
        ]);

        CRUD::addColumn([
            'name' => 'detected_stage_id',
            'label' => __('broken_glass.detected_stage'),
            'type' => 'text',
            'limit' => 100,
            'searchLogic' => false,
            'value' => fn ($entry) => $entry->detectedStage?->title ?? '—',
        ]);

        CRUD::addColumn([
            'name' => 'description',
            'label' => __('broken_glass.comment'),
            'type' => 'text',
            'limit' => 2000,
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('description', 'ilike', '%' . $searchTerm . '%');
            },
            'value' => fn ($entry) => filled($entry->description) ? $entry->description : '',
        ]);

        CRUD::addColumn([
            'name' => 'user_name',
            'label' => __('broken_glass.author'),
            'type' => 'text',
            'limit' => 100,
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('user_name', 'ilike', '%' . $searchTerm . '%');
            },
            'value' => fn ($entry) => $entry->user_label,
        ]);

        CRUD::addColumn([
            'name' => 'status',
            'label' => __('broken_glass.status'),
            'type' => 'custom_html',
            'escaped' => false,
            'orderable' => false,
            'searchLogic' => false,
            'value' => fn ($entry) => $this->statusBadge($entry),
        ]);

        CRUD::addColumn([
            'name' => 'confirmed_at',
            'label' => __('broken_glass.confirmed'),
            'type' => 'custom_html',
            'escaped' => false,
            'searchLogic' => false,
            'value' => function ($entry) {
                if (! $entry->isConfirmed()) {
                    return '<span class="text-muted">—</span>';
                }

                return e($entry->confirmed_by_name ?: '—') . '<br><small class="text-muted">'
                    . e($entry->confirmed_at->format('Y-m-d H:i')) . '</small>';
            },
        ]);
    }

    protected function setupFilters(): void
    {
        CRUD::addFilter([
            'name' => 'created_at',
            'type' => 'date_range',
            'label' => __('broken_glass.date'),
        ], false, function ($value) {
            $dates = json_decode($value, true);

            if (! is_array($dates)) {
                return;
            }

            if (! empty($dates['from'])) {
                $this->crud->addClause('where', 'created_at', '>=', Carbon::parse($dates['from'])->startOfDay()->toDateTimeString());
            }
            if (! empty($dates['to'])) {
                $this->crud->addClause('where', 'created_at', '<=', Carbon::parse($dates['to'])->endOfDay()->toDateTimeString());
            }
        });

        CRUD::addFilter([
            'name' => 'status',
            'type' => 'dropdown',
            'label' => __('broken_glass.status'),
        ], [
            BrokenGlass::STATUS_PENDING => __('broken_glass.statuses.pending'),
            BrokenGlass::STATUS_CONFIRMED => __('broken_glass.statuses.confirmed'),
            BrokenGlass::STATUS_LEGACY => __('broken_glass.statuses.legacy'),
        ], function ($value) {
            $this->crud->addClause('withStatus', $value);
        });

        CRUD::addFilter([
            'name' => 'machine_id',
            'type' => 'dropdown',
            'label' => __('broken_glass.machine'),
        ], fn () => Machine::query()->ordered()->pluck('name', 'id')->toArray(), function ($value) {
            $this->crud->addClause('where', 'machine_id', (int) $value);
        });

        CRUD::addFilter([
            'name' => 'detected_stage_id',
            'type' => 'dropdown',
            'label' => __('broken_glass.detected_stage'),
        ], fn () => BrokenGlass::detectionStages()->pluck('title', 'id')->toArray(), function ($value) {
            $this->crud->addClause('where', 'detected_stage_id', (int) $value);
        });

        CRUD::addFilter([
            'name' => 'user_id',
            'type' => 'select2',
            'label' => __('broken_glass.author'),
        ], fn () => User::query()->orderBy('name')->pluck('name', 'id')->toArray(), function ($value) {
            $this->crud->addClause('where', 'user_id', (int) $value);
        });

        CRUD::addFilter([
            'name' => 'client_id',
            'type' => 'select2',
            'label' => __('broken_glass.client'),
        ], fn () => Client::query()->orderBy('name')->pluck('name', 'id')->toArray(), function ($value) {
            $this->crud->addClause('where', 'client_id', (int) $value);
        });

        CRUD::addFilter([
            'name' => 'order_id',
            'type' => 'text',
            'label' => __('broken_glass.order_id'),
        ], false, function ($value) {
            if (ctype_digit(trim((string) $value))) {
                $this->crud->addClause('where', 'order_id', (int) $value);
            }
        });

        CRUD::addFilter([
            'name' => 'piece_id',
            'type' => 'text',
            'label' => __('broken_glass.piece_id'),
        ], false, function ($value) {
            if (ctype_digit(trim((string) $value))) {
                $this->crud->addClause('where', 'piece_id', (int) $value);
            }
        });
    }

    protected function setupUpdateOperation()
    {
        CRUD::setValidation(BrokenGlassRequest::class);

        /** @var BrokenGlass|null $entry */
        $entry = $this->crud->getCurrentEntry() ?: null;
        $maxArea = $entry ? BreakDetails::formatArea($entry->damaged_area ?? 0) : null;

        CRUD::addField([
            'name' => 'record_summary',
            'type' => 'custom_html',
            'value' => $entry ? view('admin.broken-glass.edit-summary', ['entry' => $entry])->render() : '',
        ]);

        CRUD::addField([
            'name' => 'usable_area',
            'label' => __('broken_glass.usable_area'),
            'type' => 'text',
            'attributes' => ['inputmode' => 'decimal', 'autocomplete' => 'off'],
            'hint' => __('broken_glass.fields.usable_area_hint', ['max' => $maxArea ?? '—']),
            'wrapper' => ['class' => 'form-group col-md-4'],
        ]);

        CRUD::addField([
            'name' => 'machine_id',
            'label' => __('broken_glass.machine'),
            'type' => 'select_from_array',
            'options' => Machine::query()
                ->where(fn ($q) => $q->where('is_active', true)->when($entry?->machine_id, fn ($q2, $id) => $q2->orWhere('id', $id)))
                ->ordered()
                ->pluck('name', 'id')
                ->toArray(),
            'allows_null' => false,
            'wrapper' => ['class' => 'form-group col-md-4'],
        ]);

        CRUD::addField([
            'name' => 'detected_stage_id',
            'label' => __('broken_glass.detected_stage'),
            'type' => 'select_from_array',
            'options' => BrokenGlass::detectionStages()->pluck('title', 'id')->toArray(),
            'allows_null' => false,
            'wrapper' => ['class' => 'form-group col-md-4'],
        ]);

        CRUD::addField([
            'name' => 'description',
            'label' => __('broken_glass.comment'),
            'type' => 'textarea',
            'hint' => __('broken_glass.fields.comment_hint'),
        ]);

        $this->crud->removeSaveActions(['save_and_new', 'save_and_preview']);
    }

    protected function statusBadge(BrokenGlass $entry): string
    {
        return match ($entry->currentStatus()) {
            BrokenGlass::STATUS_CONFIRMED => '<span class="badge bg-success">' . e(__('broken_glass.statuses.confirmed')) . '</span>',
            BrokenGlass::STATUS_PENDING => '<span class="badge bg-warning text-dark">' . e(__('broken_glass.statuses.pending')) . '</span>',
            default => '<span class="badge bg-secondary">' . e(__('broken_glass.statuses.legacy')) . '</span>',
        };
    }

    /**
     * m² with three decimals (the warehouse's precision), or a dash when unknown.
     */
    protected function area($value): string
    {
        return $value === null ? '—' : number_format((float) $value, 3, '.', '');
    }

    protected function sizeLabel(BrokenGlass $entry): string
    {
        $piece = $entry->piece;

        if (! $piece || ! $piece->width || ! $piece->height) {
            return '—';
        }

        $trim = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return $trim($piece->width) . ' × ' . $trim($piece->height);
    }

    protected function pieceLink(BrokenGlass $entry): string
    {
        $label = e((string) $entry->piece_id);
        $user = backpack_user();

        if ($user?->can('piece.show')) {
            return '<a href="' . e(backpack_url('piece/' . $entry->piece_id . '/show')) . '">' . $label . '</a>';
        }

        if ($entry->order_id && $this->canOpenOrderShow($user)) {
            return '<a href="' . e(backpack_url('order/' . $entry->order_id . '/show')) . '">' . $label . '</a>';
        }

        return $label;
    }

    protected function orderLink(BrokenGlass $entry): string
    {
        if (! $entry->order_id) {
            return '<span class="text-muted">—</span>';
        }

        $label = e((string) $entry->order_id);

        if ($this->canOpenOrderShow(backpack_user())) {
            return '<a href="' . e(backpack_url('order/' . $entry->order_id . '/show')) . '">' . $label . '</a>';
        }

        return $label;
    }

    protected function canOpenOrderShow($user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('order.show') || $user->can('team-order.view');
    }
}
