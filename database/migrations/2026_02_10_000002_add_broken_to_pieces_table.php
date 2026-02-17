<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('pieces', 'broken')) {
            Schema::table('pieces', function (Blueprint $table) {
                $table->unsignedInteger('broken')->default(0)->after('status');
            });
        } else {
            // Column exists (e.g. from old boolean) - alter to integer
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE pieces MODIFY broken INT UNSIGNED NOT NULL DEFAULT 0');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE pieces ALTER COLUMN broken TYPE INTEGER USING COALESCE(broken::int, 0)');
                DB::statement('ALTER TABLE pieces ALTER COLUMN broken SET DEFAULT 0');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            if (Schema::hasColumn('pieces', 'broken')) {
                $table->dropColumn('broken');
            }
        });
    }
};
