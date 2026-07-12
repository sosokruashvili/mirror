<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user saved filters for the team orders page (product/service/stage/
     * client/date). Applied automatically when the page is opened without an
     * explicit filter submission, and cleared via the Reset button.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('team_order_filters')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('team_order_filters');
        });
    }
};
