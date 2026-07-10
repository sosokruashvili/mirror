<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Full system access with all permissions'
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Can manage clients and orders'
            ],
            [
                'name' => 'Employee',
                'slug' => 'employee',
                'description' => 'Can view and edit assigned clients'
            ],
            [
                'name' => 'Viewer',
                'slug' => 'viewer',
                'description' => 'Read-only access to the system'
            ],
            [
                'name' => 'Cutting',
                'slug' => 'cutting',
                'description' => 'მოჭრა - Cutting stage'
            ],
            [
                'name' => 'Processing',
                'slug' => 'processing',
                'description' => 'დამუშავება - Processing stage'
            ],
            [
                'name' => 'Cutting/Drilling',
                'slug' => 'cutting-drilling',
                'description' => 'ჭრა/ხვრეტა - Cutting and drilling stage'
            ],
            [
                'name' => 'Assembly',
                'slug' => 'assembly',
                'description' => 'აწყობა - Assembly stage'
            ],
            [
                'name' => 'Tempering',
                'slug' => 'tempering',
                'description' => 'წრთობა - Tempering stage'
            ],
            [
                'name' => 'Curing',
                'slug' => 'curing',
                'description' => 'დამატოვება - Curing/resting stage'
            ],
            [
                'name' => 'Finishing',
                'slug' => 'finishing',
                'description' => 'გამზადება - Finishing stage'
            ]
        ];

        foreach ($roles as $role) {
            \App\Models\Role::firstOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}
