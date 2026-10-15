<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production stages a SERVICE order has completed.
 *
 * Service orders have no pieces (their services sit on the order with
 * order_service.piece_id = NULL), so there is nothing for piece_stage to hang
 * on. This is the same pivot keyed on the order instead: the order goes
 * through the stages of its services exactly like a piece does. Orders with
 * pieces never get rows here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_stage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'stage_id']);
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_stage');
    }
};
