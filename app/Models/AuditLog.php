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
        'is_batch_head' => 'boolean',
        'created_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Batching
    |--------------------------------------------------------------------------
    |
    | One user action can touch many records — saving a 30-piece order writes 30
    | rows. They share a batch_id, and a "group" is one batch narrowed to a single
    | event and subject type, which is the unit the activity log lists.
    |
    */

    /**
     * Raw SQL matching another audit_logs alias to the same group as the outer
     * row. Kept in one place so the scopes below cannot drift apart.
     */
    protected static function sameGroupAs(string $alias): string
    {
        return "{$alias}.batch_id = audit_logs.batch_id
                and {$alias}.event = audit_logs.event
                and {$alias}.subject_type is not distinct from audit_logs.subject_type";
    }

    /**
     * Keep only the head row of each group, so a bulk change is one list row.
     *
     * Reads the stored flag rather than deriving the head with a subquery: the
     * derived form cannot use an index and makes every pagination count a
     * sequential scan over the whole table.
     */
    public function scopeRepresentativeOfBatch($query)
    {
        return $query->where('audit_logs.is_batch_head', true);
    }

    /**
     * Attach how many rows the group holds, so the list can render "30 × Piece"
     * without a query per row.
     */
    public function scopeWithGroupCount($query)
    {
        return $query
            ->addSelect('audit_logs.*')
            ->selectSub(
                'select count(*) from audit_logs c where ' . static::sameGroupAs('c'),
                'group_count'
            );
    }

    /**
     * Every row in this row's group, oldest first — what the Show page expands.
     */
    public function groupMembers()
    {
        return static::query()
            ->where('batch_id', $this->batch_id)
            ->where('event', $this->event)
            ->whereRaw('subject_type is not distinct from ?', [$this->subject_type])
            ->orderBy('id');
    }

    /**
     * Size of this row's group, preferring the value the list query selected.
     */
    public function getGroupCountAttribute(): int
    {
        return (int) ($this->attributes['group_count'] ?? $this->groupMembers()->count());
    }

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
