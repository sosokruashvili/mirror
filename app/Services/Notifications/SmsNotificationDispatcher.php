<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Services\Sms\SmsException;
use App\Services\Sms\SmsMessage;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends pending sms notifications. This is the only application path that
 * should call the SMS gateway for messages the app itself created.
 */
class SmsNotificationDispatcher
{
    public function __construct(protected SmsSender $sms)
    {
    }

    /**
     * @return array{sent: int, failed: int, retried: int}
     */
    public function dispatch(int $limit = 20): array
    {
        $this->releaseStaleClaims();

        $ids = Notification::query()
            // Cabinet rows are read inside the client cabinet and are not sent.
            ->where('type', Notification::TYPE_SMS)
            ->where('status', Notification::STATUS_PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $sent = 0;
        $failed = 0;
        $retried = 0;

        foreach ($ids as $id) {
            $claimed = Notification::query()
                ->whereKey($id)
                ->where('status', Notification::STATUS_PENDING)
                ->update([
                    'status' => Notification::STATUS_SENDING,
                    'updated_at' => now(),
                ]);

            if ($claimed === 0) {
                continue;
            }

            $notification = Notification::query()->find($id);

            if (! $notification) {
                continue;
            }

            try {
                $reference = $this->referenceFor($notification);
                $result = $this->sms->send(
                    $notification->recipient,
                    SmsMessage::create($notification->body)->reference($reference)
                );

                $notification->forceFill([
                    'status' => Notification::STATUS_SENT,
                    'sent_at' => now(),
                    'last_error' => null,
                    'gateway_reference' => $result->reference ?? $reference,
                    'attempts' => $notification->attempts + 1,
                ])->save();

                $sent++;
            } catch (Throwable $e) {
                $attempts = $notification->attempts + 1;
                $retry = $e instanceof SmsException
                    && $e->isRetryable()
                    && $attempts < Notification::MAX_ATTEMPTS;

                $notification->forceFill([
                    'status' => $retry ? Notification::STATUS_PENDING : Notification::STATUS_FAILED,
                    'attempts' => $attempts,
                    'last_error' => $e->getMessage(),
                ])->save();

                Log::warning('SMS notification was not sent', [
                    'notification_id' => $notification->id,
                    'attempts' => $attempts,
                    'retry' => $retry,
                    'error' => $e->getMessage(),
                ]);

                if (! $e instanceof SmsException) {
                    report($e);
                }

                if ($retry) {
                    $retried++;
                } else {
                    $failed++;
                }
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'retried' => $retried,
        ];
    }

    /**
     * A crashed run leaves the row in "sending". Hand it back after a while
     * so the next pass can try again.
     */
    protected function releaseStaleClaims(): void
    {
        Notification::query()
            ->where('type', Notification::TYPE_SMS)
            ->where('status', Notification::STATUS_SENDING)
            ->where('updated_at', '<=', now()->subMinutes(10))
            ->update([
                'status' => Notification::STATUS_PENDING,
                'updated_at' => now(),
            ]);
    }

    /**
     * smsoffice.ge accepts a reference of at most 20 characters.
     */
    protected function referenceFor(Notification $notification): string
    {
        return substr('n'.$notification->id, 0, 20);
    }
}
