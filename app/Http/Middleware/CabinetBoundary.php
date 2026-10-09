<?php

namespace App\Http\Middleware;

use App\Support\Cabinet\Cabinet;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Separates the client cabinet from the admin panel.
 *
 * On the cabinet host the session cookie is a different name, scoped to that
 * host only, and any path outside the cabinet (including /admin) is rejected
 * before Backpack's routes can run.
 */
class CabinetBoundary
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Cabinet::isHost($request)) {
            return $next($request);
        }

        config([
            'session.cookie' => 'cabinet-session',
            'session.domain' => null,
            'session.secure' => $request->isSecure(),
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.partitioned' => false,
        ]);

        URL::forceRootUrl($request->getSchemeAndHttpHost());

        // Georgian until SetLocale applies a choice stored in this host's session.
        // Also covers pages that abort before the session middleware runs.
        app()->setLocale('ka');
        Carbon::setLocale('ka');

        if (! Cabinet::allowsPath($request->path())) {
            abort(404);
        }

        $response = $next($request);

        return $response instanceof Response ? Cabinet::secure($response) : $response;
    }
}
