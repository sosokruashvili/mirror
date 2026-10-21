<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\Order;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * The order_ready SMS: queued when production is done (status ready), so the
 * client knows to come for the order. Nothing here calls the gateway;
 * pending notifications are sent by the usual schedule.
 */
class OrderReadySms
{
    /** Global Settings switch. Off (or missing) means nothing is queued. */
    public const SETTING = 'order_ready_sms';

    public static function enabled(): bool
    {
        return (bool) setting(self::SETTING, false);
    }

    /**
     * Queue the SMS for this order's client, once per order. A problem (no
     * phone, missing template) is logged and never thrown: it must not stop
     * the stage tick that made the order ready.
     */
    public static function queue(Order $order): ?Notification
    {
        if (! self::enabled()) {
            return null;
        }

        try {
            // A savepoint inside the caller's transaction, so a failed insert
            // cannot abort the stage change around it.
            return DB::transaction(function () use ($order) {
                $template = SmsTemplate::findByKey(SmsTemplate::KEY_ORDER_READY);

                if (self::query($order)->where('sms_template_id', $template->getKey())->exists()) {
                    return null;
                }

                return Notification::queueSms(
                    (string) $order->client?->routeNotificationForSms(),
                    $template,
                    $order
                );
            });
        } catch (Throwable $e) {
            Log::warning('Order ready SMS was not queued', [
                'order_id' => $order->getKey(),
                'error' => $e->getMessage(),
            ]);

            if (! $e instanceof InvalidArgumentException) {
                report($e);
            }

            return null;
        }
    }

    /**
     * Drop the SMS if it is still waiting when the order is handed out: the
     * client already has the order. One the sender claimed or sent stays.
     *
     * @return int Number of rows removed.
     */
    public static function cancelPending(Order $order): int
    {
        $template = SmsTemplate::query()->where('key', SmsTemplate::KEY_ORDER_READY)->first();

        if (! $template) {
            return 0;
        }

        return self::query($order)
            ->where('sms_template_id', $template->getKey())
            ->where('status', Notification::STATUS_PENDING)
            ->delete();
    }

    protected static function query(Order $order)
    {
        return Notification::query()
            ->where('type', Notification::TYPE_SMS)
            ->where('notifiable_type', $order->getMorphClass())
            ->where('notifiable_id', $order->getKey());
    }
}
