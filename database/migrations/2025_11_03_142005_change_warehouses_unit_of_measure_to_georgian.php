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
        // Drop the existing check constraint first
        DB::statement('ALTER TABLE warehouses DROP CONSTRAINT IF EXISTS warehouses_unit_of_measure_check');
        
        // Update existing data from English to Georgian values
        DB::statement("UPDATE warehouses SET unit_of_measure = 'ცალი' WHERE unit_of_measure = 'pieces'");
        DB::statement("UPDATE warehouses SET unit_of_measure = 'კვ.მ' WHERE unit_of_measure = 'cubic_meters'");
        
        // Add new check constraint with Georgian values
        DB::statement("ALTER TABLE warehouses ADD CONSTRAINT warehouses_unit_of_measure_check CHECK (unit_of_measure::text = ANY (ARRAY['ცალი'::character varying, 'კვ.მ'::character varying]::text[]))");
        
        // Change default value
        DB::statement("ALTER TABLE warehouses ALTER COLUMN unit_of_measure SET DEFAULT 'ცალი'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the Georgian constraint first
        DB::statement('ALTER TABLE warehouses DROP CONSTRAINT IF EXISTS warehouses_unit_of_measure_check');
        
        // Update data back from Georgian to English
        DB::statement("UPDATE warehouses SET unit_of_measure = 'pieces' WHERE unit_of_measure = 'ცალი'");
        DB::statement("UPDATE warehouses SET unit_of_measure = 'cubic_meters' WHERE unit_of_measure = 'კვ.მ'");
        
        // Restore original constraint with English values
        DB::statement("ALTER TABLE warehouses ADD CONSTRAINT warehouses_unit_of_measure_check CHECK (unit_of_measure::text = ANY (ARRAY['pieces'::character varying, 'cubic_meters'::character varying]::text[]))");
        
        // Restore default value
        DB::statement("ALTER TABLE warehouses ALTER COLUMN unit_of_measure SET DEFAULT 'pieces'");
    }
};
