<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Development driver: writes the message to the log instead of sending it.
 */
class LogSmsSender implements SmsSender
{
    public function __construct(protected ?string $sender = null)
    {
    }

    public function send(string|iterable $to, string|SmsMessage $message): SmsResult
    {
        $message = SmsMessage::from($message);
        $destinations = PhoneNumber::normalizeMany($to);

        if ($destinations === []) {
            throw SmsException::invalidDestination(PhoneNumber::describe($to));
        }

        Log::info('[SMS log driver] '.$message->content, [
            'destinations' => $destinations,
            'sender' => $message->sender ?? $this->sender,
            'urgent' => $message->urgent,
            'reference' => $message->reference,
            'scheduled_at' => $message->scheduledAt?->format(DATE_ATOM),
        ]);

        return new SmsResult($destinations, $message->content, $message->reference, 'Logged (not sent)');
    }

    public function balance(): ?int
    {
        return null;
    }

    public function status(string $destination, string $reference): ?string
    {
        return 'Unknown';
    }
}
