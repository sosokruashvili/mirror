<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouses', 'quantity')) {
                $table->unsignedInteger('quantity')->default(0)->after('product_id');
            }
            if (!Schema::hasColumn('warehouses', 'area')) {
                $table->decimal('area', 10, 3)->default(0)->after('quantity');
            }
        });

        // Migrate existing data from the generic value/unit_of_measure combo
        // into the dedicated quantity (ცალი) and area (კვ.მ) columns.
        if (Schema::hasColumn('warehouses', 'unit_of_measure') && Schema::hasColumn('warehouses', 'value')) {
            DB::table('warehouses')->where('unit_of_measure', 'ცალი')->update([
                'quantity' => DB::raw('ROUND(value)'),
            ]);
            DB::table('warehouses')->where('unit_of_measure', 'კვ.მ')->update([
                'area' => DB::raw('value'),
            ]);
        }

        Schema::table('warehouses', function (Blueprint $table) {
            if (Schema::hasColumn('warehouses', 'unit_of_measure')) {
                $table->dropColumn('unit_of_measure');
            }
            if (Schema::hasColumn('warehouses', 'value')) {
                $table->dropColumn('value');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouses', 'unit_of_measure')) {
                $table->string('unit_of_measure')->default('ცალი')->after('product_id');
            }
            if (!Schema::hasColumn('warehouses', 'value')) {
                $table->decimal('value', 13, 3)->default(0)->after('unit_of_measure');
            }
        });

        // Best-effort restore: collapse quantity/area back into value/unit_of_measure.
        if (Schema::hasColumn('warehouses', 'quantity') && Schema::hasColumn('warehouses', 'area')) {
            DB::table('warehouses')->where('area', '>', 0)->update([
                'unit_of_measure' => 'კვ.მ',
                'value' => DB::raw('area'),
            ]);
            DB::table('warehouses')->where('area', '<=', 0)->update([
                'unit_of_measure' => 'ცალი',
                'value' => DB::raw('quantity'),
            ]);
        }

        Schema::table('warehouses', function (Blueprint $table) {
            if (Schema::hasColumn('warehouses', 'quantity')) {
                $table->dropColumn('quantity');
            }
            if (Schema::hasColumn('warehouses', 'area')) {
                $table->dropColumn('area');
            }
        });
    }
};
