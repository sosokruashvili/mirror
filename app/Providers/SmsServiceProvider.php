<?php

namespace App\Providers;

use App\Services\Sms\ArraySmsSender;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsOfficeSender;
use App\Services\Sms\SmsSender;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsSender::class, function ($app) {
            $driver = (string) config('sms.driver', 'log');

            return match ($driver) {
                'smsoffice' => new SmsOfficeSender($app->make(HttpFactory::class), (array) config('sms.smsoffice', [])),
                'log' => new LogSmsSender(config('sms.smsoffice.sender')),
                'array' => new ArraySmsSender(),
                default => throw new InvalidArgumentException("Unsupported SMS driver [{$driver}]."),
            };
        });

        $this->app->alias(SmsSender::class, 'sms');
    }
}
