<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A per-user snapshot of the team-orders filters, taken with the Save
     * button. Unlike `team_order_filters` (the active selection, cleared by
     * Reset), this survives Reset so a complex filter can be brought back
     * with the Restore button instead of being rebuilt from scratch.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('team_order_saved_filters')->nullable()->after('team_order_filters');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('team_order_saved_filters');
        });
    }
};
