<?php

namespace App\Services\Sms;

/**
 * Outcome of a successful send() call.
 */
final class SmsResult
{
    /**
     * @param list<string> $destinations Normalised numbers the message was accepted for.
     * @param array<string, mixed>|null $output Raw "Output" object returned by the gateway.
     */
    public function __construct(
        public readonly array $destinations,
        public readonly string $content,
        public readonly ?string $reference = null,
        public readonly string $gatewayMessage = '',
        public readonly ?array $output = null,
    ) {
    }

    public function count(): int
    {
        return count($this->destinations);
    }
}
