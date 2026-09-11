<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One broken-glass event, written by App\Support\Auditing\BrokenGlassLogger.
 *
 * A row is one break. `quantity` says how many pieces that single break sent back
 * through production (a size-group break on the team board records one row for the
 * whole group) and is REPORTING ONLY — Piece::getBrokenCount(), which drives the
 * order's warehouse expense, still counts rows, because one break has always meant
 * one extra sheet of material.
 *
 * order_id / client_id / client_name are denormalized copies kept so the read-only
 * list page (BrokenGlassCrudController) can filter without joining through pieces.
 */
class BrokenGlass extends Model
{
    use CrudTrait;

    protected $table = 'broken_glasses';

    protected $fillable = [
        'piece_id',
        'description',
        'quantity',
        'user_id',
        'user_name',
        'order_id',
        'client_id',
        'client_name',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * The piece this broken glass record belongs to.
     */
    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    /**
     * The author of this record — whoever clicked "gatqda", which is not
     * necessarily the person who physically broke the glass. Null for rows whose
     * causer could not be recovered from the audit trail.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
