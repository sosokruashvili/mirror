<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Contracts\Auth\StatefulGuard;
use Backpack\CRUD\app\Library\Auth\BackpackUserProvider;

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
        // Administrators are limitless: every ability check passes for them,
        // so no page or action ever needs an explicit admin exception.
        // Returning null (not false) lets non-admins fall through to the
        // normal permission checks.
        Gate::before(function ($user) {
            return $user?->hasRole('admin') ? true : null;
        });
    }
}
