<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_balances', function (Blueprint $table) {
            $table->decimal('starting_balance', 12, 2)->default(0)->after('balance_date');
        });
    }

    public function down(): void
    {
        Schema::table('client_balances', function (Blueprint $table) {
            $table->dropColumn('starting_balance');
        });
    }
};
