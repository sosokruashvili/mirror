<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nested expense categories (Backpack ReorderOperation nested-set columns).
     * Seeded with the six labels that used to be hardcoded on CashierExpense.
     */
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedInteger('lft')->default(0);
            $table->unsignedInteger('rgt')->default(0);
            $table->unsignedInteger('depth')->default(1);
            $table->timestamps();

            $table->foreign('parent_id')
                ->references('id')
                ->on('expense_categories')
                ->nullOnDelete();
        });

        $now = now();
        $names = [
            'Food',
            'Accessories',
            'Consumable Materials',
            'Installation',
            'Salary',
            'General',
        ];

        // Flat roots: each node occupies two nested-set slots (lft, rgt).
        foreach ($names as $index => $name) {
            $lft = ($index * 2) + 1;
            DB::table('expense_categories')->insert([
                'name' => $name,
                'parent_id' => null,
                'lft' => $lft,
                'rgt' => $lft + 1,
                'depth' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
