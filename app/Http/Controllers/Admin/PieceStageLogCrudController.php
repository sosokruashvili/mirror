<?php

namespace App\Http\Controllers\Admin;

use App\Models\Client;
use App\Models\PieceStageLog;
use App\Models\Stage;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Read-only list of piece production-stage changes (piece_stage_logs).
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PieceStageLogCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;

    public function setup()
    {
        CRUD::setModel(PieceStageLog::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/piece-stage-log');
        CRUD::setEntityNameStrings(__('piece_stage_log.entity'), __('piece_stage_log.entity_plural'));
    }

    protected function setupListOperation()
    {
        $this->crud->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        $this->crud->with(['user', 'stage', 'client', 'piece', 'order']);

        CRUD::addColumn([
            'name' => 'user_label',
            'label' => __('piece_stage_log.user'),
            'type' => 'text',
            'orderable' => false,
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('user_name', 'like', '%' . $searchTerm . '%');
            },
        ]);

        CRUD::addColumn([
            'name' => 'stage',
            'label' => __('piece_stage_log.stage'),
            'type' => 'custom_html',
            'escaped' => false,
            'orderable' => false,
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('stage', function ($q) use ($searchTerm) {
                    $q->where('title', 'like', '%' . $searchTerm . '%')
                        ->orWhere('name', 'like', '%' . $searchTerm . '%');
                });
            },
            'value' => fn ($entry) => $this->stageBadge($entry),
        ]);

        CRUD::addColumn([
            'name' => 'action',
            'label' => __('piece_stage_log.action'),
            'type' => 'custom_html',
            'escaped' => false,
            'value' => fn ($entry) => '<span class="badge bg-' . e($entry->action_color) . '">'
                . e($entry->action_label) . '</span>',
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'label' => __('piece_stage_log.date'),
            'type' => 'datetime',
        ]);

        CRUD::addColumn([
            'name' => 'piece_id',
            'label' => __('piece_stage_log.piece_id'),
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
            'name' => 'order_id',
            'label' => __('piece_stage_log.order_id'),
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
            'label' => __('piece_stage_log.client'),
            'type' => 'custom_html',
            'escaped' => false,
            'orderable' => false,
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhere('client_name', 'like', '%' . $searchTerm . '%');
            },
            'value' => fn ($entry) => $this->clientLink($entry),
        ]);

        $this->setupFilters();
    }

    protected function setupFilters(): void
    {
        CRUD::addFilter([
            'name' => 'user_id',
            'type' => 'select2',
            'label' => __('piece_stage_log.user'),
        ], function () {
            return User::query()->orderBy('name')->pluck('name', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'user_id', $value);
        });

        CRUD::addFilter([
            'name' => 'stage_id',
            'type' => 'select2',
            'label' => __('piece_stage_log.stage'),
        ], function () {
            return Stage::ordered()->pluck('title', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'stage_id', $value);
        });

        CRUD::addFilter([
            'name' => 'action',
            'type' => 'select2',
            'label' => __('piece_stage_log.action'),
        ], [
            PieceStageLog::ACTION_COMPLETED => __('piece_stage_log.actions.completed'),
            PieceStageLog::ACTION_CLEARED => __('piece_stage_log.actions.cleared'),
        ], function ($value) {
            $this->crud->addClause('where', 'action', $value);
        });

        CRUD::addFilter([
            'name' => 'created_at',
            'type' => 'date_range',
            'label' => __('piece_stage_log.date'),
        ], false, function ($value) {
            $dates = json_decode($value);
            if (! empty($dates->from)) {
                $this->crud->addClause('where', 'created_at', '>=', $dates->from . ' 00:00:00');
            }
            if (! empty($dates->to)) {
                $this->crud->addClause('where', 'created_at', '<=', $dates->to . ' 23:59:59');
            }
        });

        CRUD::addFilter([
            'name' => 'piece_id',
            'type' => 'text',
            'label' => __('piece_stage_log.piece_id'),
        ], false, function ($value) {
            $this->crud->addClause('where', 'piece_id', $value);
        });

        CRUD::addFilter([
            'name' => 'order_id',
            'type' => 'text',
            'label' => __('piece_stage_log.order_id'),
        ], false, function ($value) {
            $this->crud->addClause('where', 'order_id', $value);
        });

        CRUD::addFilter([
            'name' => 'client_id',
            'type' => 'select2',
            'label' => __('piece_stage_log.client'),
        ], function () {
            return Client::query()->orderBy('name')->pluck('name', 'id')->toArray();
        }, function ($value) {
            $this->crud->addClause('where', 'client_id', $value);
        });
    }

    protected function stageBadge(PieceStageLog $entry): string
    {
        $slug = $entry->stage?->name;
        $label = e($entry->stage_label);

        if (! $slug) {
            return $label;
        }

        $bg = piece_stage_color($slug);
        $text = piece_stage_text_color($slug);

        if ($bg === '') {
            return $label;
        }

        return '<span class="badge" style="background-color: ' . e($bg) . '; color: ' . e($text) . ';">'
            . $label . '</span>';
    }

    protected function pieceLink(PieceStageLog $entry): string
    {
        if (! $entry->piece_id) {
            return '<span class="text-muted">—</span>';
        }

        $label = e((string) $entry->piece_id);
        $user = backpack_user();

        if ($user?->can('piece.show') && $entry->piece) {
            return '<a href="' . e(backpack_url('piece/' . $entry->piece_id . '/show')) . '">' . $label . '</a>';
        }

        if ($entry->order_id && $this->canOpenOrderShow($user)) {
            return '<a href="' . e(backpack_url('order/' . $entry->order_id . '/show')) . '">' . $label . '</a>';
        }

        return $label;
    }

    protected function orderLink(PieceStageLog $entry): string
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

    protected function clientLink(PieceStageLog $entry): string
    {
        $label = e($entry->client_label);

        if ($label === '—') {
            return '<span class="text-muted">—</span>';
        }

        $user = backpack_user();

        if ($user?->can('client.show') && $entry->client_id && $entry->client) {
            return '<a href="' . e(backpack_url('client/' . $entry->client_id . '/show')) . '">' . $label . '</a>';
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
