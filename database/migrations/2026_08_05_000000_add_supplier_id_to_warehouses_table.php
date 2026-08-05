<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            // Which supplier the material came from. Nullable: rows entered before
            // this column existed have no supplier, and the supplier is not always
            // known at the time the stock is recorded.
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('product_id')
                ->constrained('suppliers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
