<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->onDelete('set null');
            $table->decimal('amount_gel', 10, 2);
            $table->decimal('currency_rate', 10, 4);
            $table->decimal('amount_usd', 10, 2);
            $table->enum('method', ['Cash', 'Transfer']);
            $table->enum('status', ['Paid', 'Pending'])->default('Pending');
            $table->string('file')->nullable();
            $table->dateTime('payment_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

