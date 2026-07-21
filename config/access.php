<?php

/*
|--------------------------------------------------------------------------
| Page / action access map
|--------------------------------------------------------------------------
|
| Single source of truth for role-based page access. Each key is a "page"
| (matching its admin route slug where possible) and lists the actions that
| page supports. The AccessPermissionSeeder turns every page.action pair into
| a `type = 'page'` permission named "{page}.{action}" (e.g. "order.update").
|
| Enforcement:
|   - CRUD controllers use the ChecksAccess trait and denyAccess() any action
|     the user lacks (see App\Http\Controllers\Admin\Traits\ChecksAccess).
|   - Custom pages are guarded by the `can:{page}.{action}` route middleware.
|   - The sidebar hides items the user cannot access (@can('order.list') ...).
|
| Administrators bypass all of this via the Gate::before() rule in
| AppServiceProvider, so they are always limitless.
|
| Add a new page = add one line here, then `php artisan db:seed
| --class=AccessPermissionSeeder`.
|
*/

return [

    'pages' => [

        // ----- Operational pages -----
        'order'            => ['label' => 'Orders',           'actions' => ['list', 'create', 'update', 'delete', 'show']],
        'client'           => ['label' => 'Clients',          'actions' => ['list', 'create', 'update', 'delete', 'show']],
        'client-balance'   => ['label' => 'Client Balances',  'actions' => ['list']],
        'product'          => ['label' => 'Products',         'actions' => ['list', 'create', 'update', 'delete', 'show']],
        'service'          => ['label' => 'Services',         'actions' => ['list', 'create', 'update', 'delete', 'show']],
        'piece'            => ['label' => 'Pieces',           'actions' => ['list', 'update', 'delete', 'show']],
        'stage'            => ['label' => 'Stages',           'actions' => ['list', 'create', 'update', 'delete', 'show']],
        'warehouse'        => ['label' => 'Warehouse Stock',  'actions' => ['list', 'create', 'update', 'delete', 'show']],
        'warehouse-expense' => ['label' => 'Warehouse Expenses', 'actions' => ['list']],
        'supplier'         => ['label' => 'Suppliers',        'actions' => ['list', 'create', 'update', 'delete', 'show']],

        'payment'          => ['label' => 'Payments',         'actions' => ['list', 'create', 'update', 'delete', 'show']],
        'cashier'           => ['label' => 'Cashier Balance',     'actions' => ['list']],
        'cashier-expense'   => ['label' => 'Cashier Expenses',    'actions' => ['list', 'create', 'update', 'delete', 'show']],
        'expense-category'  => ['label' => 'Expense Categories',  'actions' => ['list', 'create', 'update', 'delete', 'reorder']],
        'custom-price'      => ['label' => 'Custom Prices',       'actions' => ['list', 'create', 'update', 'delete', 'show']],

        // ----- Custom (non-CRUD) pages -----
        'settings'         => ['label' => 'Global Settings',  'actions' => ['view', 'update']],
        'team-order'       => ['label' => 'Team Orders',      'actions' => ['view', 'operate']],

        // ----- Administration pages -----
        'user'             => ['label' => 'Users',            'actions' => ['list', 'create', 'update', 'delete', 'show']],
        'role'             => ['label' => 'Roles',            'actions' => ['list', 'create', 'update', 'delete', 'show']],
        'permission'       => ['label' => 'Permissions',      'actions' => ['list', 'create', 'update', 'delete', 'show']],
        'audit-log'        => ['label' => 'Activity Log',     'actions' => ['list', 'show']],
    ],

    /*
    | Human-friendly labels for actions, used when generating permission
    | descriptions shown in the admin checklist (e.g. "Orders — Create").
    */
    'action_labels' => [
        'list'    => 'View list',
        'show'    => 'View details',
        'create'  => 'Create',
        'update'  => 'Edit',
        'delete'  => 'Delete',
        'reorder' => 'Reorder',
        'view'    => 'View',
        'operate' => 'Operate',
    ],
];
