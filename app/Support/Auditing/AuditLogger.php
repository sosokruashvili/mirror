<?php

namespace App\Support\Auditing;

use App\Models\AuditLog;
use App\Models\ClientBalance;
use App\Models\PieceStageLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns Eloquent model events into audit_logs rows.
 *
 * A single instance is subscribed to the global "eloquent.{event}: *" events in
 * App\Providers\AuditServiceProvider, so every model that fires created / updated
 * / deleted / restored is recorded without touching the model itself.
 *
 * Design rules:
 *   - Never break the host action: all work is wrapped so a logging failure is
 *     swallowed (and reported to the Laravel log) rather than bubbling up.
 *   - Never recurse: the AuditLog model itself is ignored.
 *   - Never leak secrets: password / token style fields are redacted.
 *   - Only meaningful diffs: unchanged updates and timestamp-only churn are
 *     skipped.
 */
class AuditLogger
{
    /**
     * Model classes that must never be audited (would recurse or add noise).
     */
    protected array $ignoredModels = [
        AuditLog::class,
        // Derived rows: one per client per day, rewritten on every order or
        // payment change. Auditing them buries real edits under recomputations.
        ClientBalance::class,
        // Piece stage history has its own table and list page; logging each
        // insert here would duplicate every workshop stage click.
        PieceStageLog::class,
    ];

    /**
     * Attribute names that are never stored, whatever model they belong to.
     */
    protected array $redactedKeys = [
        'password',
        'remember_token',
        'api_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Columns excluded from update diffs because they change on every save.
     */
    protected array $ignoredDiffKeys = [
        'updated_at',
        'created_at',
    ];

    /**
     * Groups every row written during one request / job, so a single user action
     * that touches many records reads as one entry in the activity log.
     *
     * The logger is a singleton, so this is minted once per process lifecycle and
     * reset between queued jobs by App\Providers\AuditServiceProvider.
     */
    protected ?string $batchId = null;

    /**
     * Groups within the current batch that already have a head row, keyed by
     * event + subject type. Lets the list view find the one row representing a
     * bulk change with an indexed lookup instead of a correlated subquery.
     */
    protected array $batchHeads = [];

    /**
     * Start a new batch. Called at the boundaries of a unit of work.
     */
    public function newBatch(): void
    {
        $this->batchId = null;
        $this->batchHeads = [];
    }

    protected function batchId(): string
    {
        return $this->batchId ??= (string) Str::uuid();
    }

    public function created(Model $model): void
    {
        $this->record('created', $model, null, $this->attributes($model));
    }

    public function updated(Model $model): void
    {
        $changed = $this->stripIgnoredDiffKeys($model->getChanges());

        if (empty($changed)) {
            return; // nothing meaningful changed (e.g. timestamp-only touch)
        }

        $new = $this->redact($changed);
        $old = $this->redact(array_intersect_key($model->getOriginal(), $changed));

        $this->record('updated', $model, $old, $new);
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model, $this->attributes($model), null);
    }

    public function restored(Model $model): void
    {
        $this->record('restored', $model, null, $this->attributes($model));
    }

    /**
     * Persist one audit row, never letting a failure interrupt the caller.
     */
    protected function record(string $event, Model $model, ?array $old, ?array $new): void
    {
        if ($this->shouldIgnore($model)) {
            return;
        }

        try {
            $causer = $this->resolveCauser();
            $groupKey = $event . '|' . $model->getMorphClass();
            $isHead = ! isset($this->batchHeads[$groupKey]);

            AuditLog::create([
                'batch_id'      => $this->batchId(),
                'is_batch_head' => $isHead,
                'event'         => $event,
                'subject_type' => $model->getMorphClass(),
                'subject_id'   => $model->getKey(),
                'causer_id'    => $causer?->getKey(),
                'causer_name'  => $this->causerName($causer),
                'old_values'   => $old,
                'new_values'   => $new,
                'ip_address'   => $this->requestValue(fn () => request()->ip()),
                'url'          => $this->requestValue(fn () => request()->fullUrl()),
            ]);

            // Only after a successful insert, so a failed head does not leave the
            // rest of its group headless and therefore hidden from the list.
            $this->batchHeads[$groupKey] = true;
        } catch (\Throwable $e) {
            // Auditing must never take down the actual request.
            Log::warning('Audit logging failed: ' . $e->getMessage(), [
                'event'   => $event,
                'subject' => $model->getMorphClass() . '#' . $model->getKey(),
            ]);
        }
    }

    protected function shouldIgnore(Model $model): bool
    {
        foreach ($this->ignoredModels as $class) {
            if ($model instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Full, redacted attribute set for a model (used for create / delete).
     */
    protected function attributes(Model $model): array
    {
        return $this->redact($model->getAttributes());
    }

    protected function stripIgnoredDiffKeys(array $values): array
    {
        return array_diff_key($values, array_flip($this->ignoredDiffKeys));
    }

    /**
     * Replace sensitive values with a placeholder without dropping the key,
     * so the log still shows that the field was involved.
     */
    protected function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array($key, $this->redactedKeys, true)) {
                $values[$key] = '••••••';
            }
        }

        return $values;
    }

    /**
     * The acting user: prefer Backpack's guard, fall back to the default guard.
     */
    protected function resolveCauser(): ?Model
    {
        if (function_exists('backpack_user') && ($user = backpack_user())) {
            return $user;
        }

        return auth()->user();
    }

    /**
     * Best-effort display name snapshot for the causer.
     */
    protected function causerName(?Model $causer): ?string
    {
        if (! $causer) {
            return null;
        }

        return $causer->name
            ?? $causer->email
            ?? (class_basename($causer) . ' #' . $causer->getKey());
    }

    /**
     * Read request context only when there is a real HTTP request.
     */
    protected function requestValue(callable $callback): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        try {
            return $callback();
        } catch (\Throwable) {
            return null;
        }
    }
}
