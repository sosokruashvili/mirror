<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Groups audit_logs rows into batches.
 *
 * One user action often touches many records at once — editing an order with 30
 * pieces writes 30 rows. batch_id ties those rows to the single request that
 * produced them, so the activity log can show one collapsed entry ("30 × Piece
 * updated") while keeping every individual row intact underneath.
 *
 * Written by App\Support\Auditing\AuditLogger, which mints one id per request
 * (or per queued job / console command).
 *
 * is_batch_head marks the one row per group that the list view shows. It is a
 * stored flag rather than a "id = (select min(id) ...)" test because that test
 * cannot be indexed: it forces a sequential scan on every pagination count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->after('id');

            // Default true so a row is never invisible: a writer that does not
            // know about batching still produces a listable entry.
            $table->boolean('is_batch_head')->default(true)->after('batch_id');
        });

        // Backfill history so existing rows collapse the same way new ones will.
        // Rows from the same causer, on the same URL, within the same second were
        // in practice a single request — that is exactly the burst shape the
        // bulk-update noise takes.
        DB::statement(<<<'SQL'
            UPDATE audit_logs a
               SET batch_id = g.batch_id
              FROM (
                    SELECT causer_id,
                           url,
                           date_trunc('second', created_at) AS sec,
                           gen_random_uuid() AS batch_id
                      FROM audit_logs
                     WHERE created_at IS NOT NULL
                     GROUP BY causer_id, url, date_trunc('second', created_at)
                   ) g
             WHERE a.causer_id IS NOT DISTINCT FROM g.causer_id
               AND a.url IS NOT DISTINCT FROM g.url
               AND date_trunc('second', a.created_at) = g.sec
        SQL);

        // Anything the grouping pass could not reach (e.g. a null created_at)
        // becomes a batch of one, so the column is never null.
        DB::statement('UPDATE audit_logs SET batch_id = gen_random_uuid() WHERE batch_id IS NULL');

        // A group is one batch narrowed to a single event + subject type; its
        // head is the earliest row. Everything else collapses underneath it.
        //
        // Done as one aggregate pass plus an anti-join rather than a correlated
        // subquery per row — the correlated form has no index to use at this
        // point in the migration and degrades into a scan per row.
        // GROUP BY treats NULL subject_type as equal, matching the
        // IS NOT DISTINCT FROM test the runtime grouping uses.
        DB::statement(<<<'SQL'
            UPDATE audit_logs
               SET is_batch_head = false
             WHERE id NOT IN (
                   SELECT min(id)
                     FROM audit_logs
                    GROUP BY batch_id, event, subject_type
                   )
        SQL);

        // Raw rather than $table->uuid('batch_id')->nullable(false)->change():
        // Laravel's change() rebuilds the column, which rewrites every row —
        // including this table's two JSON columns — and took over three minutes
        // here against a few tens of thousands of rows. The direct constraint is
        // instant, and holds the lock for correspondingly less time.
        DB::statement('ALTER TABLE audit_logs ALTER COLUMN batch_id SET NOT NULL');

        Schema::table('audit_logs', function (Blueprint $table) {
            // Serves the per-group member lookup (count, and the Show expansion).
            $table->index(['batch_id', 'subject_type', 'event', 'id'], 'audit_logs_batch_group_index');
        });

        // Partial index: the list only ever asks for heads, newest first, so this
        // covers both the page and its count without touching the other rows.
        DB::statement('CREATE INDEX audit_logs_batch_head_index
                         ON audit_logs (created_at DESC, id DESC)
                      WHERE is_batch_head');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS audit_logs_batch_head_index');

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_batch_group_index');
            $table->dropColumn(['batch_id', 'is_batch_head']);
        });
    }
};
