<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/**
 * Ensures the original six cashier-expense category labels exist as roots.
 * Safe to re-run (firstOrCreate by name). Nested children are left untouched.
 */
class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Food',
            'Accessories',
            'Consumable Materials',
            'Installation',
            'Salary',
            'General',
        ];

        foreach ($names as $name) {
            ExpenseCategory::firstOrCreate(
                ['name' => $name, 'parent_id' => null],
                ['depth' => 1]
            );
        }

        ExpenseCategory::rebuildTree();
    }
}
