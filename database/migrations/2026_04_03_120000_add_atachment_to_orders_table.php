<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'atachment')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('atachment')->nullable()->after('paid');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('orders', 'atachment')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('atachment');
            });
        }
    }
};
