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
        // Drop the existing check constraint
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_product_type_check');
        
        // Add the new check constraint with 'mirror' included
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_product_type_check CHECK (product_type::text = ANY (ARRAY['glass'::character varying, 'film'::character varying, 'extra'::character varying, 'mirror'::character varying]::text[]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new constraint
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_product_type_check');
        
        // Restore the original constraint without 'mirror'
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_product_type_check CHECK (product_type::text = ANY (ARRAY['glass'::character varying, 'film'::character varying, 'extra'::character varying]::text[]))");
    }
};
