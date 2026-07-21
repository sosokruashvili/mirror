<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,       // production-stage permissions
            AccessPermissionSeeder::class, // page/action access permissions
            RoleSeeder::class,             // roles + default access matrix
            ExpenseCategorySeeder::class,  // default cashier expense categories
        ]);
    }
}
