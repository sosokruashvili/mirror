<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the active admin-panel language to every web request.
 *
 * Resolution order (first hit wins):
 *   1. the logged-in user's `locale` column  - follows them across devices
 *   2. the `locale` session key              - covers guests (login page) and
 *                                              users who never picked one
 *   3. config('app.locale')                  - the app-wide default
 *
 * Anything not listed in config('locales.supported') is ignored, so a stale
 * session value or a hand-edited DB row can never put the panel into a
 * locale we have no translations for.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        app()->setLocale($locale);

        // Carbon powers Backpack's date columns/filters, so it needs the same
        // language - otherwise you get Georgian labels next to English months.
        Carbon::setLocale($locale);

        config(['backpack.ui.html_direction' => config("locales.supported.$locale.dir", 'ltr')]);

        return $next($request);
    }

    protected function resolve(Request $request): string
    {
        $user = backpack_auth()->user();

        foreach ([$user?->locale, $request->session()->get('locale')] as $candidate) {
            if ($this->isSupported($candidate)) {
                return $candidate;
            }
        }

        return config('app.locale');
    }

    protected function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, config('locales.supported', []));
    }
}
