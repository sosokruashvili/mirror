<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change perimeter column from smallint to decimal(10,2) for PostgreSQL
        DB::statement('ALTER TABLE order_service ALTER COLUMN perimeter TYPE DECIMAL(10,2) USING perimeter::numeric(10,2)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert perimeter column back to smallint
        DB::statement('ALTER TABLE order_service ALTER COLUMN perimeter TYPE SMALLINT USING perimeter::smallint');
    }
};
