<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\DevEnvironment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-session database switch for developers.
 *
 * When the session carries `db_connection = dev` and the signed-in user holds
 * the `developer` role, the default connection for this request becomes the
 * development database. The role is verified against PRODUCTION: the auth
 * user (with roles) is resolved before anything is switched, so a forged
 * session key held by a non-developer is simply dropped.
 *
 * Runs in the `web` group after StartSession and before SubstituteBindings /
 * any app middleware that queries the database (see bootstrap/app.php).
 * On a dev host (App\Support\DevEnvironment) the switch is already done at
 * bootstrap and this middleware does nothing.
 */
class SwitchDatabaseConnection
{
    public function handle(Request $request, Closure $next): Response
    {
        if (DevEnvironment::isActive() || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();

        if ($session->get(DevEnvironment::SESSION_KEY) !== 'dev') {
            return $next($request);
        }

        // Resolve the user on the production connection, roles included, so
        // isDeveloper() never needs to touch the database again this request.
        $user = backpack_auth()->user();

        if ($user instanceof User) {
            $user->loadMissing('roles');
        }

        if (! $user instanceof User || ! $user->hasRole('developer')) {
            $session->forget(DevEnvironment::SESSION_KEY);

            return $next($request);
        }

        DevEnvironment::useDev();

        return $next($request);
    }
}
