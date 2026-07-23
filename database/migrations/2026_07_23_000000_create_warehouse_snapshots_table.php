<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            // Total warehouse area (m²) held for the product as of snapshot_date.
            $table->decimal('warehouse_area', 13, 3)->default(0);
            // Total order expenses (m²) consumed as of snapshot_date (incl. offcut).
            $table->decimal('expenses', 13, 3)->default(0);
            // warehouse_area - expenses (may be negative when oversold).
            $table->decimal('remaining', 13, 3)->default(0);
            // Product offcut % at snapshot time, and the offcut portion of expenses.
            $table->decimal('offcut_percent', 8, 2)->default(0);
            $table->decimal('offcut_area', 13, 3)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'snapshot_date']);
            $table->index('snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_snapshots');
    }
};
