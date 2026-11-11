<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One payment can now cover several orders.
 *
 * order_payment replaces payments.order_id as the link between the two. Its
 * amount column is the order's share of the payment. Nobody types it in:
 * App\Services\PaymentAllocation works it out from the order totals and
 * rewrites it whenever the client's payments change. It is stored only so the
 * order list, its totals and the dashboard can read an order's paid amount
 * without redoing the split for every row.
 *
 * Existing links are copied over with the full payment amount as the share,
 * which is what a single-order payment always gets. Links to orders that were
 * deleted (payments.order_id has no foreign key) are left behind.
 *
 * payments.order_id itself is kept for now and is no longer read or written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_payment', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('order_id');
            $table->decimal('amount', 12, 2)->default(0);

            $table->primary(['payment_id', 'order_id']);
            $table->index('order_id');

            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });

        DB::statement('
            INSERT INTO order_payment (payment_id, order_id, amount)
            SELECT p.id, p.order_id, p.amount_gel
            FROM payments p
            JOIN orders o ON o.id = p.order_id
            ON CONFLICT DO NOTHING
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payment');
    }
};
