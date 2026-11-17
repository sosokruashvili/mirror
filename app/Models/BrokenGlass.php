<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One broken-glass event, written by App\Support\Auditing\BrokenGlassLogger.
 *
 * A row is one break. `quantity` says how many pieces that single break sent back
 * through production (a size-group break on the team board records one row for the
 * whole group) and is REPORTING ONLY — Piece::getBrokenCount(), which drives the
 * order's warehouse expense, still counts rows, because one break has always meant
 * one extra sheet of material.
 *
 * order_id / client_id / client_name are denormalized copies kept so the list page
 * (BrokenGlassCrudController) can filter without joining through pieces.
 *
 * Break details (since 2026-11): the team records the machine, the stage at which
 * the damage was found, and how many m² of the sheet are still usable. A production
 * lead confirms the record; confirming returns usable_area to stock as warehouse
 * corrections (App\Services\BrokenGlassConfirmation). Rows written before the
 * details existed have no machine — see isLegacy() — and are never confirmed.
 */
class BrokenGlass extends Model
{
    use CrudTrait;

    /**
     * Stage names (stages.name) offered as "where the damage was found", in the
     * order the client listed them. Titles come from the stages table.
     */
    public const DETECTION_STAGE_NAMES = [
        'cutting',          // მოჭრა
        'processing',       // დამუშავება
        'tempering',        // წრთობა
        'cutting-drilling', // ჭრა/ხვრეტა
        'curing',           // დამატოვება
        'finished',         // გატანილია
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_LEGACY = 'legacy';

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
        'machine_id',
        'detected_stage_id',
        'damaged_area',
        'usable_area',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'damaged_area' => 'decimal:4',
        'usable_area' => 'decimal:4',
        'created_at' => 'datetime',
        'confirmed_at' => 'datetime',
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

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function detectedStage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'detected_stage_id');
    }

    /**
     * Stock returned by confirming this break (one row per product on the order).
     */
    public function warehouseCorrections(): HasMany
    {
        return $this->hasMany(WarehouseCorrection::class);
    }

    /**
     * The stages offered as "where the damage was found", in DETECTION_STAGE_NAMES order.
     *
     * @return Collection<int, Stage>
     */
    public static function detectionStages(): Collection
    {
        $order = array_flip(self::DETECTION_STAGE_NAMES);

        return Stage::query()
            ->whereIn('name', self::DETECTION_STAGE_NAMES)
            ->get()
            ->sortBy(fn (Stage $stage) => $order[$stage->name] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Recorded before the dialog asked for a machine; nothing to confirm.
     */
    public function isLegacy(): bool
    {
        return $this->machine_id === null;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    /**
     * Waiting for the production lead: editable and confirmable.
     */
    public function isPending(): bool
    {
        return ! $this->isLegacy() && ! $this->isConfirmed();
    }

    public function currentStatus(): string
    {
        if ($this->isConfirmed()) {
            return self::STATUS_CONFIRMED;
        }

        return $this->isLegacy() ? self::STATUS_LEGACY : self::STATUS_PENDING;
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            self::STATUS_CONFIRMED => $query->whereNotNull('confirmed_at'),
            self::STATUS_PENDING => $query->whereNull('confirmed_at')->whereNotNull('machine_id'),
            self::STATUS_LEGACY => $query->whereNull('confirmed_at')->whereNull('machine_id'),
            default => $query,
        };
    }

    /**
     * Damaged m² that cannot be used any more. Null when either side is unknown.
     */
    public function getLossAreaAttribute(): ?float
    {
        if ($this->damaged_area === null || $this->usable_area === null) {
            return null;
        }

        return round(max(0, (float) $this->damaged_area - (float) $this->usable_area), 4);
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
