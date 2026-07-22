<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cutting loss (whole mm) that a service adds to a piece's size on the team
     * orders page. Defaults to 0 (no extra size added).
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->integer('cutloss')->default(0)->after('price_gel');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('cutloss');
        });
    }
};
