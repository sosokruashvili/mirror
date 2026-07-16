<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Production-stage capabilities.
     */
    public function run(): void
    {
        $permissions = [
            'cutting' => 'Cutting (მოჭრა)',
            'processing' => 'Processing (დამუშავება)',
            'cutting-drilling' => 'Cutting/Drilling (ჭრა/ხვრეტა)',
            'tempering' => 'Tempering (წრთობა)',
            'assembly' => 'Assembly (აწყობა)',
            'curing' => 'Curing (დამატოვება)',
            'finishing' => 'Finishing (გამზადება)',
        ];

        foreach ($permissions as $name => $description) {
            Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description, 'type' => 'stage']
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
