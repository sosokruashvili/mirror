<?php

namespace App\Models\Concerns;

use App\Models\Client;
use App\Models\Notification;
use App\Models\SmsTemplate;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;

/**
 * Lets a model queue an outbound notification. This does not send anything;
 * the scheduler delivers sms rows.
 */
trait QueuesNotifications
{
    public function queuedNotifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    /**
     * Queue an SMS rendered from $template. Does not send it.
     *
     * @param  array<string, scalar|null>  $variables
     */
    public function queueSms(SmsTemplate $template, ?string $recipient = null, array $variables = []): Notification
    {
        if ($recipient === null && method_exists($this, 'routeNotificationForSms')) {
            $recipient = $this->routeNotificationForSms();
        }

        return Notification::queueSms((string) $recipient, $template, $this, $variables);
    }

    /**
     * Show this text in the client's cabinet. Does not send an SMS.
     */
    public function queueCabinet(string $body): Notification
    {
        if (! $this instanceof Client) {
            throw new InvalidArgumentException(__('notification.errors.client'));
        }

        return Notification::queueCabinet($body, $this);
    }
}
