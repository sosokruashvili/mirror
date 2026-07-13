<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('balance_date');
            $table->decimal('payments_total', 12, 2)->default(0);
            $table->decimal('orders_total', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['client_id', 'balance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_balances');
    }
};
