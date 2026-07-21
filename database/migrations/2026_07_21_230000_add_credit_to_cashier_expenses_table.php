<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_expenses', function (Blueprint $table) {
            $table->decimal('credit', 12, 2)
                ->default(0)
                ->after('amount_gel');
        });
    }

    public function down(): void
    {
        Schema::table('cashier_expenses', function (Blueprint $table) {
            $table->dropColumn('credit');
        });
    }
};
