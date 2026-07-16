<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Syncs the `type = 'page'` permissions from config/access.php.
 *
 * Safe to re-run: creates missing permissions, refreshes their labels, and
 * removes stale page permissions whose page/action no longer exists in config.
 * Never touches `type = 'stage'` permissions (see PermissionSeeder).
 */
class AccessPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $actionLabels = config('access.action_labels', []);
        $desired = [];

        foreach (config('access.pages', []) as $page => $definition) {
            foreach ($definition['actions'] as $action) {
                $name = "{$page}.{$action}";
                $actionLabel = $actionLabels[$action] ?? ucfirst($action);
                $desired[$name] = "{$definition['label']} — {$actionLabel}";
            }
        }

        foreach ($desired as $name => $description) {
            Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description, 'type' => 'page']
            );
        }

        // Drop page permissions that are no longer defined in config.
        Permission::where('type', 'page')
            ->whereNotIn('name', array_keys($desired))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
