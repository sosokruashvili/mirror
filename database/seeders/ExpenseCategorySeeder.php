<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Applies the Georgian expense-category tree and remaps any legacy English
 * expense categories. Delegates to `expenses:apply-category-tree`.
 */
class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('expenses:apply-category-tree', ['--delete-old' => true]);
        $this->command?->getOutput()?->write(Artisan::output());
    }
}
