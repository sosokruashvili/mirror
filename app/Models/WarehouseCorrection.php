<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * A manual, hand-entered adjustment to a product's remaining warehouse stock.
 *
 * Warehouse rows record material coming in and orders record material going out;
 * a correction covers everything else — stocktake differences, breakage, material
 * found or returned. Each correction is a permanent ledger row rather than an edit
 * to an existing record, so the reason and the author stay attached to it. The
 * global audit logger (App\Support\Auditing\AuditLogger) additionally records any
 * later edit or deletion.
 *
 * `area` is signed and reads as an adjustment to remaining stock: negative writes
 * stock off, positive adds it back. It applies from `effective_date` onward, so a
 * snapshot for an earlier day is unaffected.
 */
class WarehouseCorrection extends Model
{
    use CrudTrait;

    protected $fillable = [
        'product_id',
        'area',
        'effective_date',
        'reason',
        'user_id',
    ];

    protected $casts = [
        'area' => 'decimal:3',
        'effective_date' => 'date',
    ];

    /**
     * Stamp the author server-side.
     *
     * Deliberately not a form field: Backpack strips request input that has no
     * registered field, and an author taken from the form could be tampered with.
     * Only set on insert, so later edits keep the original author.
     */
    protected static function booted(): void
    {
        static::creating(function (WarehouseCorrection $correction) {
            $correction->user_id ??= backpack_user()?->id;
        });
    }

    /**
     * The product whose stock this corrects.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The user who entered the correction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
