<?php

namespace App\Notifications\Channels;

use App\Services\Sms\SmsMessage;
use App\Services\Sms\SmsResult;
use App\Services\Sms\SmsSender;
use Illuminate\Notifications\Notification;

/**
 * Laravel notification channel backed by the configured SmsSender.
 *
 * Notifications return `toSms($notifiable)` as a string or SmsMessage and
 * list `SmsChannel::class` in via(). The notifiable supplies the number via
 * `routeNotificationForSms()` (Client and User already do).
 */
class SmsChannel
{
    public function __construct(protected SmsSender $sms)
    {
    }

    public function send(object $notifiable, Notification $notification): ?SmsResult
    {
        if (! method_exists($notification, 'toSms')) {
            return null;
        }

        $to = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('sms', $notification)
            : null;

        if (empty($to)) {
            return null;
        }

        $message = $notification->toSms($notifiable);

        if ($message === null || $message === '') {
            return null;
        }

        return $this->sms->send($to, SmsMessage::from($message));
    }
}
