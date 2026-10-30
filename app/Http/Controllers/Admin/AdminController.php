<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\AdminController as BackpackAdminController;

/**
 * Backpack's dashboard controller (bound over it in AppServiceProvider), with
 * the dashboard limited to the dashboard.view permission. That permission is
 * granted to no role, so only administrators see the dashboard, via the
 * Gate::before() bypass.
 *
 * Login and the /admin root both land on the dashboard, so everyone else is
 * redirected to the first page they can open instead of getting a 403.
 */
class AdminController extends BackpackAdminController
{
    /**
     * Where users without the dashboard land, in order of preference:
     * permission => admin path.
     */
    private const LANDING_PAGES = [
        'order.list' => 'order',
        'team-order.view' => 'team/orders',
        'client.list' => 'client',
        'payment.list' => 'payment',
        'cashier.list' => 'cashier',
        'warehouse.list' => 'warehouse',
        'product.list' => 'product',
        'service.list' => 'service',
        'supplier.list' => 'supplier',
        'client-balance.list' => 'client-balance',
        'user-stats.view' => 'user-stats',
    ];

    public function dashboard()
    {
        $user = backpack_user();

        if ($user->can('dashboard.view')) {
            return parent::dashboard();
        }

        foreach (self::LANDING_PAGES as $ability => $path) {
            if ($user->can($ability)) {
                return redirect(backpack_url($path));
            }
        }

        abort(403, 'You do not have permission to access this page.');
    }
}
