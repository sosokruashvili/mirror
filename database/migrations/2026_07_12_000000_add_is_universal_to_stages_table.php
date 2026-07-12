<?php

use App\Models\Stage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A "universal" stage applies to every piece regardless of which services are
 * attached to it (e.g. მოჭრა is always the first step, დასრულება the last).
 * Non-universal stages come from a piece's services (services.stage_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->boolean('is_universal')->default(false)->after('position');
        });

        // Cutting and completion apply to every piece regardless of services.
        DB::table('stages')
            ->whereIn('name', ['cutting', 'completion'])
            ->update(['is_universal' => true]);

        // Raw update bypasses the model's saved() hook, so drop the cached list.
        Cache::forget(Stage::CACHE_KEY);
    }

    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->dropColumn('is_universal');
        });
    }
};
