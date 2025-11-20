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
        \App\Models\Currency::setRate();
    })->dailyAt('20:00');

});


Artisan::command('update:currency', function () {
    \App\Models\Currency::setRate();
    $this->info('Currency rates updated successfully.');
})->purpose('Manually update currency rates');

