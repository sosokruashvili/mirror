<?php

namespace App\Http\Middleware;

use App\Models\ClientAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabinet session guard. A staff session is never accepted here, and a
 * cabinet session dies when staff disable the login or change the password.
 */
class AuthenticateCabinet
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessionUser = Auth::guard('cabinet')->user();

        if (! $sessionUser instanceof ClientAccount) {
            return redirect()->guest(route('cabinet.login'));
        }

        $account = ClientAccount::query()->with('client')->find($sessionUser->getAuthIdentifier());
        $version = (int) $request->session()->get('cabinet_session_version');

        if (
            ! $account
            || ! $account->is_active
            || ! $account->client
            || $version !== (int) $account->session_version
        ) {
            $this->endSession($request);

            return redirect()
                ->route('cabinet.login')
                ->with('status', __('cabinet.session_ended'));
        }

        $request->attributes->set('cabinetAccount', $account);
        view()->share('cabinetAccount', $account);
        view()->share('cabinetClient', $account->client);

        return $next($request);
    }

    protected function endSession(Request $request): void
    {
        Auth::guard('cabinet')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
