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
        // Change area column from smallint to decimal(10,2) for PostgreSQL
        DB::statement('ALTER TABLE order_service ALTER COLUMN area TYPE DECIMAL(10,2) USING area::numeric(10,2)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert area column back to smallint
        DB::statement('ALTER TABLE order_service ALTER COLUMN area TYPE SMALLINT USING area::smallint');
    }
};
