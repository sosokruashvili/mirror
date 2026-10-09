<?php

namespace App\Providers;

use App\Models\Client;
use App\Services\CabinetAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind custom LoginController
        $this->app->bind(
            \Backpack\CRUD\app\Http\Controllers\Auth\LoginController::class,
            \App\Http\Controllers\Auth\LoginController::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The super roles (config/access.php) are limitless: every ability
        // check passes for them, so no page or action ever needs an explicit
        // exception. Returning null (not false) lets everyone else fall through
        // to the normal permission checks.
        Gate::before(function ($user) {
            return $user instanceof \App\Models\User && $user->isSuperAdmin() ? true : null;
        });

        Client::saved(function (Client $client) {
            app(CabinetAccess::class)->sync($client);
        });

        RateLimiter::for('cabinet-login', function (Request $request) {
            $phone = \App\Support\Cabinet\Cabinet::phoneDigits((string) $request->input('phone', ''));

            return [
                Limit::perMinute(5)->by('cabinet-phone|'.$request->ip().'|'.$phone),
                Limit::perMinute(15)->by('cabinet-ip|'.$request->ip()),
            ];
        });
    }
}
