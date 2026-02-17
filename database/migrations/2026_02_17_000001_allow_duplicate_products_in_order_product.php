<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow multiple rows with the same (order_id, product_id) so an order
     * can contain the same product multiple times (e.g. lamix with 2 identical glasses).
     */
    public function up(): void
    {
        Schema::table('order_product', function (Blueprint $table) {
            $table->dropUnique('order_product_order_id_product_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_product', function (Blueprint $table) {
            $table->unique(['order_id', 'product_id'], 'order_product_order_id_product_id_unique');
        });
    }
};
