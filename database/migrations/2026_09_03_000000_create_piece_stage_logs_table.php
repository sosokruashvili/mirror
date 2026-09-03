<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only history of piece production-stage changes (completed / cleared).
 *
 * The piece_stage pivot only holds current completions and is deleted on
 * uncheck, so it cannot answer "who changed this, when". Rows here are written
 * by App\Support\Auditing\PieceStageLogger whenever a real stage change happens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piece_stage_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('piece_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('client_name')->nullable();

            $table->unsignedBigInteger('stage_id')->nullable()->index();

            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();

            // completed | cleared
            $table->string('action', 20)->index();

            $table->timestamp('created_at')->nullable()->index();
            $table->index(['created_at', 'id']);
        });

        // Existing completions become "completed" rows dated at completed_at.
        // Unchecks from before this table existed cannot be recovered.
        DB::statement("
            INSERT INTO piece_stage_logs (
                piece_id, order_id, client_id, client_name,
                stage_id, user_id, user_name, action, created_at
            )
            SELECT
                ps.piece_id,
                p.order_id,
                o.client_id,
                c.name,
                ps.stage_id,
                ps.user_id,
                u.name,
                'completed',
                COALESCE(ps.completed_at, ps.created_at, NOW())
            FROM piece_stage ps
            INNER JOIN pieces p ON p.id = ps.piece_id
            LEFT JOIN orders o ON o.id = p.order_id
            LEFT JOIN clients c ON c.id = o.client_id
            LEFT JOIN users u ON u.id = ps.user_id
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('piece_stage_logs');
    }
};
