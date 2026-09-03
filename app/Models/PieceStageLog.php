<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable piece production-stage change (completed or cleared).
 *
 * Written by App\Support\Auditing\PieceStageLogger. The list page is read-only
 * (see PieceStageLogCrudController).
 */
class PieceStageLog extends Model
{
    use CrudTrait;

    public const ACTION_COMPLETED = 'completed';
    public const ACTION_CLEARED = 'cleared';

    protected $table = 'piece_stage_logs';

    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function getUserLabelAttribute(): string
    {
        if ($this->user_name) {
            return $this->user_name;
        }

        return $this->user_id ? ('User #' . $this->user_id) : '—';
    }

    public function getClientLabelAttribute(): string
    {
        if ($this->client?->name) {
            return $this->client->name;
        }

        if ($this->client_name) {
            return $this->client_name;
        }

        return $this->client_id ? ('Client #' . $this->client_id) : '—';
    }

    public function getStageLabelAttribute(): string
    {
        return $this->stage?->title
            ?? $this->stage?->name
            ?? ($this->stage_id ? ('#' . $this->stage_id) : '—');
    }

    public function getActionLabelAttribute(): string
    {
        return __('piece_stage_log.actions.' . $this->action);
    }

    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_COMPLETED => 'success',
            self::ACTION_CLEARED => 'secondary',
            default => 'secondary',
        };
    }
}
