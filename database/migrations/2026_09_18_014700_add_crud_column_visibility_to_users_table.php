<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user DataTables column-visibility maps, keyed by CRUD route
     * (e.g. "admin/order"). Survives logout and later sessions, unlike the
     * browser's DataTables state which expires after two hours.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('crud_column_visibility')->nullable()->after('team_order_saved_filters');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('crud_column_visibility');
        });
    }
};
