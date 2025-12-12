<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectTeamUsers
{
    /**
     * Routes that team users are allowed to access.
     *
     * @var array
     */
    protected $allowedRoutes = [
        'team.orders',
        'backpack.logout',
        'backpack.login',
    ];

    /**
     * Paths that team users are allowed to access.
     *
     * @var array
     */
    protected $allowedPaths = [
        'team/orders',
        'logout',
        'login',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if user is authenticated and has team role
        if (backpack_auth()->check()) {
            $user = backpack_user();
            
            if ($user && $user->hasRole('team')) {
                $routeName = $request->route() ? $request->route()->getName() : null;
                $path = $request->path();
                $adminPrefix = config('backpack.base.route_prefix', 'admin');
                
                // Check if route name is explicitly allowed
                $isAllowedRoute = $routeName && in_array($routeName, $this->allowedRoutes);
                
                // Check if path is the team orders page or logout/login
                $isTeamOrdersPage = str_contains($path, 'team/orders');
                $isLogout = str_contains($path, 'logout') || $request->isMethod('post') && str_contains($path, 'logout');
                $isLogin = str_contains($path, 'login');
                
                // Allow only: team orders page, logout, and login
                if (!$isAllowedRoute && !$isTeamOrdersPage && !$isLogout && !$isLogin) {
                    // For AJAX/JSON requests, return 403
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['error' => 'Access denied'], 403);
                    }
                    return redirect()->route('team.orders');
                }
            }
        }

        return $next($request);
    }
}

