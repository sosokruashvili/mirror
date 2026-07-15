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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('type')->default('Order');
        });

        // Payments already attached to an order are order payments; the rest are debt payments.
        DB::statement("UPDATE payments SET type = CASE WHEN order_id IS NULL THEN 'Debt' ELSE 'Order' END");

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_type_check CHECK (type IN ('Order', 'Debt'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_type_check');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
