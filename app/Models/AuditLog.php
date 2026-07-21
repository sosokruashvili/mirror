<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A single, immutable audit-trail entry.
 *
 * Written automatically by App\Support\Auditing\AuditLogger. Nothing in the app
 * edits or deletes these rows, so the model intentionally exposes no create /
 * update / delete behaviour beyond the initial insert (see AuditLogCrudController,
 * which registers only the List and Show operations).
 */
class AuditLog extends Model
{
    use CrudTrait;

    protected $table = 'audit_logs';

    /** Only created_at is maintained; rows are never updated. */
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The admin user who performed the action, if still present.
     *
     * There is no DB-level foreign key (the log outlives the user), so this may
     * resolve to null even when causer_id is set — use causer_label for display.
     */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /**
     * The affected record, if it still exists. Deleted subjects resolve to null.
     *
     * Not an Eloquent relationship (subject_type/id are stored raw so the log
     * survives deletion), so this is a plain lookup — never accessed as a
     * dynamic `->subject` property.
     */
    public function resolveSubject()
    {
        if (! $this->subject_type || ! class_exists($this->subject_type)) {
            return null;
        }

        return $this->subject_type::find($this->subject_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Short model name for the subject, e.g. "App\Models\Order" => "Order".
     */
    public function getSubjectModelAttribute(): string
    {
        return $this->subject_type ? class_basename($this->subject_type) : '—';
    }

    /**
     * Human label for the subject, e.g. "Order #42".
     */
    public function getSubjectLabelAttribute(): string
    {
        if (! $this->subject_type) {
            return '—';
        }

        return $this->subject_model . ' #' . $this->subject_id;
    }

    /**
     * Who performed the action, falling back to the stored snapshot, then System.
     */
    public function getCauserLabelAttribute(): string
    {
        if ($this->causer_name) {
            return $this->causer_name;
        }

        return $this->causer_id ? ('User #' . $this->causer_id) : 'System';
    }

    /**
     * Number of fields touched, used for a compact list column.
     */
    public function getChangedFieldsCountAttribute(): int
    {
        return count($this->new_values ?? $this->old_values ?? []);
    }

    /**
     * Bootstrap colour for the event badge.
     */
    public function getEventColorAttribute(): string
    {
        return match ($this->event) {
            'created'  => 'success',
            'updated'  => 'warning',
            'deleted'  => 'danger',
            'restored' => 'info',
            default    => 'secondary',
        };
    }

    /**
     * Pretty label for the event, e.g. "created" => "Created".
     */
    public function getEventLabelAttribute(): string
    {
        return Str::of($this->event)->headline()->toString();
    }
}
