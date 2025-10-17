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
        // Drop and recreate the currencies table with proper id column
        DB::statement('DROP TABLE IF EXISTS currencies CASCADE');
        DB::statement('
            CREATE TABLE currencies (
                id SERIAL PRIMARY KEY,
                rate_usd DECIMAL(10, 4) NOT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS currencies CASCADE');
    }
};

