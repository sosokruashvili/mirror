<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Create the daily cutting page permission and give it to administrators and
     * managers, without resetting the rest of each role's matrix.
     */
    public function up(): void
    {
        $permission = Permission::updateOrCreate(
            ['name' => 'cutting-stats.view', 'guard_name' => 'web'],
            ['description' => 'Cutting Stats — View', 'type' => 'page']
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::query()->whereIn('slug', ['admin', 'developer', 'manager'])->get() as $role) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('type', 'page')
            ->where('name', 'cutting-stats.view')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
