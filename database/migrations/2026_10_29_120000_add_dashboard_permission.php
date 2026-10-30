<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Create the dashboard page permission and give it to administrators and
     * developers only, so it shows ticked in their role checklist and can be
     * granted to another role later.
     */
    public function up(): void
    {
        $permission = Permission::updateOrCreate(
            ['name' => 'dashboard.view', 'guard_name' => 'web'],
            ['description' => 'Dashboard — View', 'type' => 'page']
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::query()->whereIn('slug', ['admin', 'developer'])->get() as $role) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('type', 'page')
            ->where('name', 'dashboard.view')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
