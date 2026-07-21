<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_expenses', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('type')
                ->constrained('expense_categories')
                ->restrictOnDelete();
        });

        // Best-effort remap: match the old free-text category to a seeded name.
        if (Schema::hasColumn('cashier_expenses', 'category')) {
            $categories = DB::table('expense_categories')->pluck('id', 'name');

            foreach ($categories as $name => $id) {
                DB::table('cashier_expenses')
                    ->where('category', $name)
                    ->update(['category_id' => $id]);
            }

            Schema::table('cashier_expenses', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }

    public function down(): void
    {
        Schema::table('cashier_expenses', function (Blueprint $table) {
            $table->string('category')->nullable()->after('type');
        });

        $categories = DB::table('expense_categories')->pluck('name', 'id');

        foreach ($categories as $id => $name) {
            DB::table('cashier_expenses')
                ->where('category_id', $id)
                ->update(['category' => $name]);
        }

        Schema::table('cashier_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
