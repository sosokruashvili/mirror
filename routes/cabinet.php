<?php

use App\Http\Controllers\Cabinet\DashboardController;
use App\Http\Controllers\Cabinet\LoginController;
use App\Http\Controllers\Cabinet\OrderController;
use App\Http\Controllers\Cabinet\PaymentController;
use Illuminate\Support\Facades\Route;

/*
| Client cabinet. Registered before the admin catch-all so this host owns "/".
| CabinetBoundary still refuses every other path, including /admin.
*/

Route::domain((string) config('cabinet.host'))->group(function () {
    Route::middleware('guest:cabinet')->group(function () {
        Route::get('/login', [LoginController::class, 'create'])->name('cabinet.login');
        Route::post('/login', [LoginController::class, 'store'])
            ->middleware('throttle:cabinet-login');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])
        ->middleware('cabinet.auth')
        ->name('cabinet.logout');

    Route::middleware('cabinet.auth')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('cabinet.home');
        Route::get('/orders', [OrderController::class, 'index'])->name('cabinet.orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])
            ->whereNumber('order')
            ->name('cabinet.orders.show');
        Route::get('/payments', [PaymentController::class, 'index'])->name('cabinet.payments.index');
    });
});
