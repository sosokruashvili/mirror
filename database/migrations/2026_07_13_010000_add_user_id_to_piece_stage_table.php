<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record which user marked each stage completed on the piece_stage pivot.
 * Nullable so historical/backfilled completions (which have no known actor)
 * and system updates are allowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piece_stage', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('stage_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('piece_stage', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
