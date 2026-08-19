<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Draft/confirmed workflow for Expenses-Purchases.
 *
 * New rows start as 'draft' (editable, and deliberately counted nowhere), and
 * only become part of the cashier balance and the supplier balances once they
 * are confirmed. Every row that already exists predates the workflow and is
 * already baked into those balances, so it is backfilled as 'confirmed' -
 * otherwise every historical balance would drop to zero.
 *
 * The values are the ones in App\Models\CashierExpense::STATUS_*; they are
 * spelled out here so this migration keeps working if those constants change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_expenses', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->after('type');
            $table->index('status');
        });

        DB::table('cashier_expenses')->update(['status' => 'confirmed']);
    }

    public function down(): void
    {
        Schema::table('cashier_expenses', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
