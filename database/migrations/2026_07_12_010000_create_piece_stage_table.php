<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot recording each production stage a piece has COMPLETED, with the time it
 * was completed. This is the authoritative record of a piece's progress.
 *
 * `pieces.stage` is kept as a denormalized cache of the highest-position
 * completed stage (see Piece::refreshStageColumn), so all existing stage-driven
 * logic (order status sync, filters, badges) keeps working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piece_stage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['piece_id', 'stage_id']);
        });

        // Backfill: the old single `pieces.stage` slug meant the piece had
        // progressed through the stages up to and including that one, so mark
        // every stage at or before that position as completed. We don't have
        // real per-stage timestamps historically, so use the piece's updated_at.
        $stages = DB::table('stages')->orderBy('position')->orderBy('id')->get();
        $posByName = [];
        foreach ($stages as $s) {
            $posByName[$s->name] = $s->position;
        }

        $now = now();

        DB::table('pieces')
            ->whereNotNull('stage')
            ->where('stage', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($pieces) use ($stages, $posByName, $now) {
                $rows = [];

                foreach ($pieces as $piece) {
                    $throughPos = $posByName[$piece->stage] ?? null;
                    if ($throughPos === null) {
                        continue;
                    }

                    $completedAt = $piece->updated_at ?? $now;

                    foreach ($stages as $stage) {
                        if ($stage->position <= $throughPos) {
                            $rows[] = [
                                'piece_id' => $piece->id,
                                'stage_id' => $stage->id,
                                'completed_at' => $completedAt,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }
                }

                if (!empty($rows)) {
                    DB::table('piece_stage')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('piece_stage');
    }
};
