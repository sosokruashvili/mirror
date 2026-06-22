<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE order_service ALTER COLUMN tape_length TYPE DECIMAL(10,2) USING tape_length::numeric(10,2)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE order_service ALTER COLUMN tape_length TYPE INTEGER USING tape_length::integer');
    }
};
