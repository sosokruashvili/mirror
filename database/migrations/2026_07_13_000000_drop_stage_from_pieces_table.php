<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the denormalized `pieces.stage` cache. The piece_stage pivot (which
 * stages a piece has completed, and when) is now the single source of truth,
 * and all read/write paths derive from it (Piece::currentStageName, the
 * order-status sync, badges, filters).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            $table->dropColumn('stage');
        });
    }

    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            $table->string('stage')->nullable()->after('height');
        });

        // Rebuild the cache as the highest-position completed stage per piece.
        $stages = DB::table('stages')->orderBy('position')->orderBy('id')->get();
        $posById = [];
        $nameById = [];
        foreach ($stages as $s) {
            $posById[$s->id] = $s->position;
            $nameById[$s->id] = $s->name;
        }

        DB::table('piece_stage')
            ->orderBy('piece_id')
            ->get()
            ->groupBy('piece_id')
            ->each(function ($rows, $pieceId) use ($posById, $nameById) {
                $highest = $rows->sortByDesc(fn ($r) => $posById[$r->stage_id] ?? 0)->first();
                if ($highest) {
                    DB::table('pieces')->where('id', $pieceId)
                        ->update(['stage' => $nameById[$highest->stage_id] ?? null]);
                }
            });
    }
};
