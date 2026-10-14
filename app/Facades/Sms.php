<?php

namespace App\Facades;

use App\Services\Sms\ArraySmsSender;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Services\Sms\SmsResult send(string|iterable $to, string|\App\Services\Sms\SmsMessage $message)
 * @method static int|null balance()
 * @method static string|null status(string $destination, string $reference)
 *
 * @see \App\Services\Sms\SmsSender
 */
class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SmsSender::class;
    }

    /**
     * Swap in the in-memory driver for the current test.
     */
    public static function fake(): ArraySmsSender
    {
        $fake = new ArraySmsSender();

        static::swap($fake);

        return $fake;
    }
}
