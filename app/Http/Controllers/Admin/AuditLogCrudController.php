<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Read-only viewer for the global audit trail (audit_logs).
 *
 * Only the List and Show operations are registered — entries are written
 * automatically by App\Support\Auditing\AuditLogger and are never created,
 * edited or deleted through the panel. Access is gated by the "audit-log"
 * page permissions declared in config/access.php via the ChecksAccess trait.
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class AuditLogCrudController extends CrudController
{
    use \App\Http\Controllers\Admin\Traits\ChecksAccess;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        CRUD::setModel(AuditLog::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/audit-log');
        CRUD::setEntityNameStrings('activity log entry', 'activity log');
    }

    protected function setupListOperation()
    {
        // Newest activity first.
        $this->crud->orderBy('created_at', 'desc')->orderBy('id', 'desc');

        $this->crud->query = $this->crud->query->withGroupCount();

        // Collapse each bulk action to a single row. The exception is drilling
        // into one record by id: there the user wants that record's own history,
        // and a row hidden behind its group's representative would look missing.
        if (! request()->filled('subject_id')) {
            $this->crud->query = $this->crud->query->representativeOfBatch();
        }

        CRUD::addColumn([
            'name' => 'created_at',
            'label' => 'When',
            'type' => 'datetime',
        ]);

        CRUD::addColumn([
            'name' => 'event',
            'label' => 'Action',
            'type' => 'custom_html',
            'escaped' => false,
            'value' => fn ($entry) => '<span class="badge bg-' . $entry->event_color . '">'
                . e($entry->event_label) . '</span>',
        ]);

        CRUD::addColumn([
            'name' => 'subject',
            'label' => 'Record',
            'type' => 'custom_html',
            'escaped' => false,
            'value' => fn ($entry) => $this->subjectLink($entry),
        ]);

        CRUD::addColumn([
            'name' => 'causer_label',
            'label' => 'By',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'changes',
            'label' => 'Changed fields',
            'type' => 'custom_html',
            'escaped' => false,
            'value' => fn ($entry) => $this->changedFieldsSummary($entry),
        ]);

        CRUD::addColumn([
            'name' => 'ip_address',
            'label' => 'IP',
            'type' => 'text',
        ]);

        $this->setupFilters();
    }

    protected function setupShowOperation()
    {
        CRUD::addColumn([
            'name' => 'created_at',
            'label' => 'When',
            'type' => 'datetime',
        ]);

        CRUD::addColumn([
            'name' => 'event',
            'label' => 'Action',
            'type' => 'custom_html',
            'escaped' => false,
            'value' => fn ($entry) => '<span class="badge bg-' . $entry->event_color . '">'
                . e($entry->event_label) . '</span>',
        ]);

        CRUD::addColumn([
            'name' => 'subject',
            'label' => 'Record',
            'type' => 'custom_html',
            'escaped' => false,
            'value' => fn ($entry) => $this->subjectLink($entry),
        ]);

        CRUD::addColumn([
            'name' => 'causer_label',
            'label' => 'Performed by',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'ip_address',
            'label' => 'IP address',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'url',
            'label' => 'URL',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'diff',
            'label' => 'Changes',
            'type' => 'custom_html',
            'escaped' => false,
            'value' => fn ($entry) => $entry->group_count > 1
                ? $this->renderGroupDiff($entry)
                : $this->renderDiff($entry),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */

    protected function setupFilters(): void
    {
        CRUD::addFilter([
            'name' => 'event',
            'type' => 'select2',
            'label' => 'Action',
        ], [
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'restored' => 'Restored',
        ], function ($value) {
            $this->crud->addClause('where', 'event', $value);
        });

        CRUD::addFilter([
            'name' => 'subject_type',
            'type' => 'select2',
            'label' => 'Record type',
        ], function () {
            // Distinct over the whole audit table; cached so it does not run on
            // every list render, when the set of types changes only on deploy.
            return Cache::remember('audit_logs.filter.subject_types', now()->addHour(), fn () => AuditLog::query()
                ->select('subject_type')
                ->whereNotNull('subject_type')
                ->distinct()
                ->orderBy('subject_type')
                ->pluck('subject_type')
                ->mapWithKeys(fn ($type) => [$type => class_basename($type)])
                ->toArray());
        }, function ($value) {
            $this->crud->addClause('where', 'subject_type', $value);
        });

        CRUD::addFilter([
            'name' => 'causer_id',
            'type' => 'select2',
            'label' => 'Performed by',
        ], function () {
            return Cache::remember('audit_logs.filter.causers', now()->addHour(), fn () => AuditLog::query()
                ->select('causer_id', 'causer_name')
                ->whereNotNull('causer_id')
                ->distinct()
                ->orderBy('causer_name')
                ->pluck('causer_name', 'causer_id')
                ->toArray());
        }, function ($value) {
            $this->crud->addClause('where', 'causer_id', $value);
        });

        CRUD::addFilter([
            'name' => 'created_at',
            'type' => 'date_range',
            'label' => 'Date range',
        ], false, function ($value) {
            $dates = json_decode($value);
            $this->crud->addClause('where', 'created_at', '>=', $dates->from . ' 00:00:00');
            $this->crud->addClause('where', 'created_at', '<=', $dates->to . ' 23:59:59');
        });

        CRUD::addFilter([
            'name' => 'subject_id',
            'type' => 'text',
            'label' => 'Record ID',
        ], false, function ($value) {
            $this->crud->addClause('where', 'subject_id', $value);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation helpers
    |--------------------------------------------------------------------------
    */

    /**
     * "Order #42", linked to the record's Show page when that page exists and
     * the record has not been deleted.
     *
     * When the row stands for a whole group ("30 × Piece"), there is no single
     * record to link to — the Show page lists the members instead.
     */
    protected function subjectLink(AuditLog $entry): string
    {
        if ($entry->group_count > 1) {
            return '<span class="badge bg-secondary me-1">' . $entry->group_count . ' ×</span> '
                . e($entry->subject_model);
        }

        $label = e($entry->subject_label);

        if (! $entry->subject_type || $entry->event === 'deleted') {
            return $label;
        }

        $slug = Str::kebab(class_basename($entry->subject_type));
        $page = config("access.pages.{$slug}");

        if ($page && in_array('show', $page['actions'] ?? [], true)) {
            $url = backpack_url($slug . '/' . $entry->subject_id . '/show');

            return '<a href="' . e($url) . '">' . $label . '</a>';
        }

        return $label;
    }

    /**
     * Comma-separated list of the fields touched, truncated for the list view.
     */
    protected function changedFieldsSummary(AuditLog $entry): string
    {
        $keys = array_keys($entry->new_values ?? $entry->old_values ?? []);

        if (empty($keys)) {
            return '<span class="text-muted">—</span>';
        }

        $shown = array_slice($keys, 0, 4);
        $text = implode(', ', array_map('e', $shown));

        if (count($keys) > 4) {
            $text .= ' <span class="text-muted">+' . (count($keys) - 4) . ' more</span>';
        }

        return '<span class="text-muted small">' . $text . '</span>';
    }

    /**
     * Field-by-field before/after table for the Show page.
     */
    protected function renderDiff(AuditLog $entry): string
    {
        $old = $entry->old_values ?? [];
        $new = $entry->new_values ?? [];
        $keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));

        if (empty($keys)) {
            return '<span class="text-muted">No field-level details recorded.</span>';
        }

        $rows = '';
        foreach ($keys as $key) {
            $rows .= '<tr>'
                . '<td class="fw-bold">' . e($key) . '</td>'
                . '<td>' . $this->formatValue($old[$key] ?? null) . '</td>'
                . '<td>' . $this->formatValue($new[$key] ?? null) . '</td>'
                . '</tr>';
        }

        return '<table class="table table-sm table-bordered mb-0">'
            . '<thead><tr>'
            . '<th style="width:20%">Field</th>'
            . '<th style="width:40%">Old value</th>'
            . '<th style="width:40%">New value</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>';
    }

    /**
     * Expanded view of a collapsed group: every record the action touched, one
     * line per changed field, so "30 × Piece updated" stays inspectable.
     */
    protected function renderGroupDiff(AuditLog $entry): string
    {
        // Enough to audit a bulk edit without rendering a runaway page.
        $limit = 200;

        $members = $entry->groupMembers()->limit($limit + 1)->get();
        $overflow = $members->count() > $limit;
        $members = $members->take($limit);

        $rows = '';
        foreach ($members as $member) {
            $old = $member->old_values ?? [];
            $new = $member->new_values ?? [];
            $keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));

            if (empty($keys)) {
                $keys = [null];
            }

            foreach ($keys as $i => $key) {
                $rows .= '<tr>'
                    // Name the record once per record, not once per field.
                    . ($i === 0
                        ? '<td rowspan="' . count($keys) . '" class="fw-bold">' . e($member->subject_label) . '</td>'
                        : '')
                    . '<td>' . ($key === null ? '<span class="text-muted">—</span>' : e($key)) . '</td>'
                    . '<td>' . $this->formatValue($key === null ? null : ($old[$key] ?? null)) . '</td>'
                    . '<td>' . $this->formatValue($key === null ? null : ($new[$key] ?? null)) . '</td>'
                    . '</tr>';
            }
        }

        $note = '<p class="text-muted small mb-2">'
            . 'One action changed ' . $entry->group_count . ' records.'
            . ($overflow ? ' Showing the first ' . $limit . '.' : '')
            . '</p>';

        return $note
            . '<table class="table table-sm table-bordered mb-0">'
            . '<thead><tr>'
            . '<th style="width:20%">Record</th>'
            . '<th style="width:20%">Field</th>'
            . '<th style="width:30%">Old value</th>'
            . '<th style="width:30%">New value</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>';
    }

    /**
     * Render a single stored value for display, coping with scalars, arrays
     * and nulls.
     */
    protected function formatValue($value): string
    {
        if ($value === null) {
            return '<span class="text-muted">—</span>';
        }

        if (is_array($value)) {
            return '<code>' . e(json_encode($value, JSON_UNESCAPED_UNICODE)) . '</code>';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return e((string) $value);
    }
}
