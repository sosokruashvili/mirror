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
        'team.orders.finish',
        'team.pieces.ready',
        'backpack.auth.logout',
        'backpack.auth.login',
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
     * Path patterns that team users are allowed to access.
     *
     * @var array
     */
    protected $allowedPathPatterns = [
        'order/*/show', // Allow order show pages
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
                
                // Check if path matches allowed patterns
                $matchesAllowedPattern = false;
                foreach ($this->allowedPathPatterns as $pattern) {
                    // Convert pattern to regex (simple wildcard matching)
                    // Pattern should match with admin prefix
                    $patternWithPrefix = $adminPrefix . '/' . $pattern;
                    // Escape special regex characters first, then replace wildcard
                    $escapedPattern = preg_quote($patternWithPrefix, '/');
                    // Replace escaped wildcard with regex pattern
                    $escapedPattern = str_replace('\*', '[^\/]+', $escapedPattern);
                    $regex = '/^' . $escapedPattern . '$/';
                    if (preg_match($regex, $path)) {
                        $matchesAllowedPattern = true;
                        break;
                    }
                }
                
                // Allow only: team orders page, logout, login, and paths matching allowed patterns
                if (!$isAllowedRoute && !$isTeamOrdersPage && !$isLogout && !$isLogin && !$matchesAllowedPattern) {
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

