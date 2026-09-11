<?php

namespace App\Http\Controllers\Admin;

use App\Models\Client;
use App\Models\Piece;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Read-only list of broken glasses: one row per piece that has been broken at
 * least once, expandable to the individual break events.
 *
 * The model is Piece, not BrokenGlass, because the page answers "which glasses
 * broke, and how often" — a piece broken three times is one row with a count of 3,
 * not three rows. Route/permission key is still `broken-glass` (ChecksAccess reads
 * the last route segment), the same model-vs-page split WarehouseExpenseCrudController uses.
 *
 * Two different numbers are shown and must not be conflated:
 *  - Breaks          = broken_glasses rows          = sheets charged to the order
 *  - Pieces affected = SUM(broken_glasses.quantity) = pieces sent back through production
 * They differ only for size-group breaks. See App\Models\BrokenGlass.
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class BrokenGlassCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;

    public function setup()
    {
        CRUD::setModel(Piece::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/broken-glass');
        CRUD::setEntityNameStrings(__('broken_glass.entity'), __('broken_glass.entity_plural'));

        $this->crud->denyAccess(['create', 'update', 'delete', 'show']);
        $this->crud->enableExportButtons();
    }

    protected function setupListOperation()
    {
        // Only pieces that actually broke, with the aggregates the columns below read.
        $this->crud->addClause('has', 'brokenGlasses');
        $this->crud->query
            ->withCount('brokenGlasses')
            ->withSum('brokenGlasses as broken_quantity_sum', 'quantity')
            ->withMin('brokenGlasses as first_broken_at', 'created_at')
            ->withMax('brokenGlasses as last_broken_at', 'created_at');

        // order.products supplies the glass type; pieces.product_id does not exist.
        $this->crud->with(['order.products', 'order.client']);
        $this->crud->orderBy('last_broken_at', 'desc');

        // Expandable rows: each break event with its date, operator and description.
        $this->crud->enableDetailsRow();
        $this->crud->setDetailsRowView('vendor.backpack.crud.details_rows.broken_glass');

        $this->setupColumns();
        $this->setupFilters();
    }

    protected function setupColumns(): void
    {
        CRUD::addColumn([
            'name' => 'id',
            'label' => __('broken_glass.piece_id'),
            'type' => 'custom_html',
            'escaped' => false,
            'value' => fn ($entry) => $this->pieceLink($entry),
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
            'name' => 'client_label',
            'label' => __('broken_glass.client'),
            'type' => 'custom_html',
            'escaped' => false,
            'orderable' => false,
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('order.client', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%');
                });
            },
            'value' => fn ($entry) => $this->clientLink($entry),
        ]);

        CRUD::addColumn([
            'name' => 'product_title',
            'label' => __('broken_glass.glass_type'),
            'type' => 'text',
            'orderable' => false,
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('order.products', function ($q) use ($searchTerm) {
                    $q->where('title', 'like', '%' . $searchTerm . '%');
                });
            },
            'value' => function ($entry) {
                if (! $entry->order) {
                    return '';
                }

                return $entry->order->products->pluck('title')->implode(' x ');
            },
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
            'name' => 'broken_glasses_count',
            'label' => __('broken_glass.breaks'),
            'type' => 'custom_html',
            'escaped' => false,
            'searchLogic' => false,
            'orderLogic' => fn ($query, $column, $direction) => $query->orderBy('broken_glasses_count', $direction),
            'value' => fn ($entry) => '<span class="badge bg-danger">' . e((string) $entry->getBrokenCount()) . '</span>',
        ]);

        CRUD::addColumn([
            'name' => 'broken_quantity_sum',
            'label' => __('broken_glass.pieces_affected'),
            'type' => 'custom_html',
            'escaped' => false,
            'searchLogic' => false,
            'orderLogic' => fn ($query, $column, $direction) => $query->orderBy('broken_quantity_sum', $direction),
            'value' => function ($entry) {
                $pieces = $entry->getBrokenPieceCount();

                // Only worth showing when a group break made it differ from the
                // number of break events; otherwise it is noise.
                if ($pieces === $entry->getBrokenCount()) {
                    return '<span class="text-muted">—</span>';
                }

                return '<span class="badge bg-warning">' . e((string) $pieces) . '</span>';
            },
        ]);

        CRUD::addColumn([
            'name' => 'first_broken_at',
            'label' => __('broken_glass.first_break'),
            'type' => 'datetime',
            'searchLogic' => false,
            'orderLogic' => fn ($query, $column, $direction) => $query->orderBy('first_broken_at', $direction),
        ]);

        CRUD::addColumn([
            'name' => 'last_broken_at',
            'label' => __('broken_glass.last_break'),
            'type' => 'datetime',
            'searchLogic' => false,
            'orderLogic' => fn ($query, $column, $direction) => $query->orderBy('last_broken_at', $direction),
        ]);
    }

    protected function setupFilters(): void
    {
        // Break dates live on the child table, so this filters by "has a break in range"
        // rather than by a column on pieces.
        CRUD::addFilter([
            'name' => 'broken_at',
            'type' => 'date_range',
            'label' => __('broken_glass.date'),
        ], false, function ($value) {
            $dates = json_decode($value, true);

            if (! is_array($dates)) {
                return;
            }

            $this->crud->addClause('whereHas', 'brokenGlasses', function ($query) use ($dates) {
                if (! empty($dates['from'])) {
                    $query->where('created_at', '>=', \Carbon\Carbon::parse($dates['from'])->startOfDay()->toDateTimeString());
                }
                if (! empty($dates['to'])) {
                    $query->where('created_at', '<=', \Carbon\Carbon::parse($dates['to'])->endOfDay()->toDateTimeString());
                }
            });
        });

        CRUD::addFilter([
            'name' => 'user_id',
            'type' => 'select2',
            'label' => __('broken_glass.author'),
        ], function () {
            return User::query()->orderBy('name')->pluck('name', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('whereHas', 'brokenGlasses', function ($query) use ($value) {
                $query->where('user_id', $value);
            });
        });

        CRUD::addFilter([
            'name' => 'client_id',
            'type' => 'select2',
            'label' => __('broken_glass.client'),
        ], function () {
            return Client::query()->orderBy('name')->pluck('name', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('whereHas', 'order', function ($query) use ($value) {
                $query->where('client_id', $value);
            });
        });

        CRUD::addFilter([
            'name' => 'repeat',
            'type' => 'simple',
            'label' => __('broken_glass.repeat_only'),
        ], false, function () {
            $this->crud->addClause('has', 'brokenGlasses', '>', 1);
        });

        CRUD::addFilter([
            'name' => 'order_id',
            'type' => 'text',
            'label' => __('broken_glass.order_id'),
        ], false, function ($value) {
            $this->crud->addClause('where', 'order_id', $value);
        });

        CRUD::addFilter([
            'name' => 'piece_id',
            'type' => 'text',
            'label' => __('broken_glass.piece_id'),
        ], false, function ($value) {
            $this->crud->addClause('where', 'id', $value);
        });
    }

    protected function sizeLabel(Piece $entry): string
    {
        $trim = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return $trim($entry->width) . ' × ' . $trim($entry->height);
    }

    protected function pieceLink(Piece $entry): string
    {
        $label = e((string) $entry->getKey());
        $user = backpack_user();

        if ($user?->can('piece.show')) {
            return '<a href="' . e(backpack_url('piece/' . $entry->getKey() . '/show')) . '">' . $label . '</a>';
        }

        if ($entry->order_id && $this->canOpenOrderShow($user)) {
            return '<a href="' . e(backpack_url('order/' . $entry->order_id . '/show')) . '">' . $label . '</a>';
        }

        return $label;
    }

    protected function orderLink(Piece $entry): string
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

    protected function clientLink(Piece $entry): string
    {
        $client = $entry->order?->client;

        if (! $client) {
            return '<span class="text-muted">—</span>';
        }

        $label = e((string) $client->name);
        $user = backpack_user();

        if ($user?->can('client.show')) {
            return '<a href="' . e(backpack_url('client/' . $client->getKey() . '/show')) . '">' . $label . '</a>';
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
