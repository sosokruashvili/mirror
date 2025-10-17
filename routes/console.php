<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

// Define an Artisan command
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule tasks after the application has booted
app()->booted(function () {
    app(Schedule::class)->call(function () {
        \App\Models\Currency::setRate(); // Replace with your logic
        Log::info('Currency rates updated successfully at ' . now());
    })->dailyAt('17:00');

    app(Schedule::class)->call(function () {
        \App\Models\Currency::setRate(); // Replace with your logic
        Log::info('Currency rates updated successfully at ' . now());
    })->dailyAt('18:00');

    app(Schedule::class)->call(function () {
        \App\Models\Currency::setRate(); // Replace with your logic
        Log::info('Currency rates updated successfully at ' . now());
    })->dailyAt('22:00');

    app(Schedule::class)->call(function () {
        \App\Models\Currency::setRate(); // Replace with your logic
        Log::info('Currency rates updated successfully at ' . now());
    })->dailyAt('8:00');

    app(Schedule::class)->call(function () {
        \App\Models\Currency::setRate(); // Replace with your logic
        Log::info('Currency rates updated successfully at ' . now());
    })->dailyAt('9:00');

    app(Schedule::class)->call(function () {
        \App\Models\Currency::setRate(); // Replace with your logic
        Log::info('Currency rates updated successfully at ' . now());
    })->dailyAt('10:00');

    app(Schedule::class)->call(function () {
        \App\Models\Currency::setRate(); // Replace with your logic
        Log::info('Currency rates updated successfully at ' . now());
    })->dailyAt('11:00');

    app(Schedule::class)->call(function () {
        \App\Models\Cachier::updateBalance();
    })->twiceDaily(1, 10);

});


Artisan::command('update:currency', function () {
    \App\Models\Currency::setRate();
    $this->info('Currency rates updated successfully.');
})->purpose('Manually update currency rates');

