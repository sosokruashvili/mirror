<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_expenses', function (Blueprint $table) {
            $table->decimal('price_usd', 10, 2)
                ->nullable()
                ->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('cashier_expenses', function (Blueprint $table) {
            $table->dropColumn('price_usd');
        });
    }
};
