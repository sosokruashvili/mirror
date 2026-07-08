<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->decimal('amount_gel', 12, 2);
            $table->text('description')->nullable();
            $table->dateTime('expense_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_expenses');
    }
};
