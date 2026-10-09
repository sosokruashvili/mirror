<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/cabinet.php';

Route::redirect('/', '/admin');

// Admin-panel language switcher. POST because it writes to the session and,
// for logged-in users, to the database. Lives on the plain "web" group so the
// login page can switch languages too, before there is an authenticated user.
Route::post('locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');
