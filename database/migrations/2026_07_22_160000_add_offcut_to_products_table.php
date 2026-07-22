<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Offcut waste (%) added on top of piece area when calculating warehouse
     * expenses for orders that use this product. Defaults to 0 (no extra).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('offcut', 5, 2)->default(0)->after('price_w');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('offcut');
        });
    }
};
