<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for page permissions on custom (non-CRUD) admin pages.
 *
 * Unlike Laravel's built-in `can:` middleware, this resolves the user through
 * backpack_user() so it works with Backpack's dedicated "backpack" auth guard.
 * Administrators pass automatically via the Gate::before() bypass.
 *
 * Usage: ->middleware('backpack.can:settings.view')
 */
class CheckBackpackPermission
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = backpack_user();

        if (! $user || ! $user->can($ability)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
