<?php

namespace App\Services\Sms;

/**
 * Test driver: keeps every "sent" message in memory for assertions.
 */
class ArraySmsSender implements SmsSender
{
    /** @var list<array{destinations: list<string>, message: SmsMessage}> */
    public array $sent = [];

    public function send(string|iterable $to, string|SmsMessage $message): SmsResult
    {
        $message = SmsMessage::from($message);
        $destinations = PhoneNumber::normalizeMany($to);

        if ($destinations === []) {
            throw SmsException::invalidDestination(PhoneNumber::describe($to));
        }

        $this->sent[] = ['destinations' => $destinations, 'message' => $message];

        return new SmsResult($destinations, $message->content, $message->reference, 'Stored in memory');
    }

    public function balance(): ?int
    {
        return null;
    }

    public function status(string $destination, string $reference): ?string
    {
        return 'Unknown';
    }

    public function sentTo(string $number): bool
    {
        $normalized = PhoneNumber::normalize($number);

        foreach ($this->sent as $entry) {
            if (in_array($normalized, $entry['destinations'], true)) {
                return true;
            }
        }

        return false;
    }

    public function flush(): void
    {
        $this->sent = [];
    }
}
