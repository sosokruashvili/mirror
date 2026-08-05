<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_expenses', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cashier_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
