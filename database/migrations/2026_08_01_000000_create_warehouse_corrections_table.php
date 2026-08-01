<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Signed adjustment to the product's remaining stock, in m².
            // Negative = write-off (less stock), positive = adds stock back.
            $table->decimal('area', 13, 3);
            // The day the correction takes effect. Snapshots on/after this date
            // include it; earlier snapshots are unaffected.
            $table->date('effective_date');
            // Why the correction was made — required, this is the audit trail.
            $table->text('reason');
            // Who entered it. Kept even if the user is later removed.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'effective_date']);
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_corrections');
    }
};
