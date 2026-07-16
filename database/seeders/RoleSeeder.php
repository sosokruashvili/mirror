<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the default roles and their page-access matrix.
 *
 * Re-runnable. Administrator is limitless via the Gate::before() rule in
 * AppServiceProvider; we also sync it with every page permission so access is
 * correct even if that bypass is ever removed.
 *
 * NOTE: run AccessPermissionSeeder before this so the page permissions exist.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Administrator', 'slug' => 'admin',    'description' => 'Full, unrestricted system access'],
            ['name' => 'Manager',       'slug' => 'manager',  'description' => 'Runs day-to-day operations; cannot manage roles'],
            ['name' => 'Employee',      'slug' => 'employee', 'description' => 'Office staff: orders, clients and pieces'],
            ['name' => 'Viewer',        'slug' => 'viewer',   'description' => 'Read-only access to operational pages'],
            ['name' => 'Team',          'slug' => 'team',     'description' => 'Workshop staff: team order processing only'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role + ['guard_name' => 'web']);
        }

        $pages = config('access.pages', []);

        // Expand a [page => actions|'*'] spec into concrete permission names,
        // ignoring actions a page does not actually support.
        $expand = function (array $spec) use ($pages): array {
            $names = [];
            foreach ($spec as $page => $actions) {
                $available = $pages[$page]['actions'] ?? [];
                $actions = $actions === '*' ? $available : array_values(array_intersect((array) $actions, $available));
                foreach ($actions as $action) {
                    $names[] = "{$page}.{$action}";
                }
            }
            return $names;
        };

        // Every page granted full access (used for admin + as a base for others).
        $allFull = [];
        foreach ($pages as $page => $def) {
            $allFull[$page] = '*';
        }

        // Operational (non-admin, non-settings, non-team) pages, read-only.
        $operational = array_diff(array_keys($pages), ['user', 'role', 'permission', 'settings', 'team-order']);
        $readOnly = [];
        foreach ($operational as $page) {
            $readOnly[$page] = ['list', 'show', 'view'];
        }

        // Manager: everything operational + settings + team-orders; users
        // view-only; no role/permission management.
        $manager = $allFull;
        unset($manager['role'], $manager['permission']);
        $manager['user'] = ['list', 'show'];

        $matrix = [
            'admin'    => $allFull,
            'manager'  => $manager,
            'employee' => [
                'order'   => ['list', 'create', 'update'],
                'client'  => ['list', 'create', 'update'],
                'piece'   => ['list', 'update'],
                'product' => ['list', 'show'],
                'service' => ['list', 'show'],
            ],
            'viewer'   => $readOnly,
            'team'     => [
                'team-order' => ['view', 'operate'],
            ],
        ];

        foreach ($matrix as $slug => $spec) {
            $role = Role::where('slug', $slug)->first();
            if ($role) {
                $role->syncPermissions($expand($spec));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
