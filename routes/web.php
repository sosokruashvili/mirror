<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/cabinet.php';

Route::redirect('/', '/admin');

// Admin-panel language switcher. POST because it writes to the session and,
// for logged-in users, to the database. Lives on the plain "web" group so the
// login page can switch languages too, before there is an authenticated user.
Route::post('locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// Debugbar on the dev host (App\Support\DevEnvironment) with a cached route
// table: `route:cache` runs on the CLI in production, where Debugbar's own
// provider skips its routes, so they would be missing from the cache and the
// toolbar could not load its assets. Register them here instead whenever the
// provider would not — they stay behind Debugbar's own `DebugbarEnabled`
// middleware (404 on production).
$debugbarRoutes = base_path('vendor/barryvdh/laravel-debugbar/src/debugbar-routes.php');
if (is_file($debugbarRoutes)
    && class_exists(\Fruitcake\LaravelDebugbar\LaravelDebugbar::class)
    && ! \Fruitcake\LaravelDebugbar\LaravelDebugbar::canBeEnabled()) {
    require $debugbarRoutes;
}
