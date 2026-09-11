<?php

use App\Models\BrokenGlass;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enriches broken_glasses so the Broken Glasses page can answer "who recorded
 * it, on which order, and how many pieces went back through production".
 *
 * Until now a break row carried only piece_id + description. The operator was
 * recoverable only from audit_logs, and a group break (TeamOrderController@markGroupBroken)
 * wrote a single row against the group's first piece with no record of the group size.
 *
 * order_id / client_id / client_name are denormalized at write time, the same way
 * piece_stage_logs does it, so the list page can filter and sort without joining
 * through pieces -> orders -> clients on every request.
 *
 * NOTE: `quantity` is reporting-only. Piece::getBrokenCount() still counts ROWS,
 * because getExpenseArea() charges one extra sheet per row and one click has always
 * meant one broken sheet. Summing quantity there would retroactively inflate the
 * warehouse expenses of every order that ever had a group break.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broken_glasses', function (Blueprint $table) {
            // How many pieces this single break sent back through production.
            $table->unsignedInteger('quantity')->default(1);

            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();

            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('client_name')->nullable();

            $table->index('created_at');
        });

        // Existing rows predate the author column; the only record of who entered
        // each break is the audit trail written by App\Support\Auditing\AuditLogger.
        // subject_type is bound rather than inlined so the class name's backslashes
        // are not mangled by PHP/Postgres string escaping.
        DB::update("
            UPDATE broken_glasses bg
            SET user_id = a.causer_id,
                user_name = a.causer_name
            FROM (
                SELECT DISTINCT ON (subject_id) subject_id, causer_id, causer_name
                FROM audit_logs
                WHERE subject_type = ?
                  AND event = 'created'
                ORDER BY subject_id, id ASC
            ) a
            WHERE a.subject_id = bg.id
        ", [BrokenGlass::class]);

        // Backfill the denormalized order/client columns from the live relations.
        DB::update("
            UPDATE broken_glasses bg
            SET order_id = p.order_id,
                client_id = o.client_id,
                client_name = c.name
            FROM pieces p
            LEFT JOIN orders o ON o.id = p.order_id
            LEFT JOIN clients c ON c.id = o.client_id
            WHERE p.id = bg.piece_id
        ");
    }

    public function down(): void
    {
        Schema::table('broken_glasses', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['client_id']);
            $table->dropIndex(['order_id']);
            $table->dropIndex(['user_id']);

            $table->dropColumn([
                'quantity',
                'user_id',
                'user_name',
                'order_id',
                'client_id',
                'client_name',
            ]);
        });
    }
};
