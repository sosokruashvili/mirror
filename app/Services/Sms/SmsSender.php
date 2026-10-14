<?php

namespace App\Services\Sms;

interface SmsSender
{
    /**
     * Send one message to one or many recipients.
     *
     * Numbers are normalised with {@see PhoneNumber::normalize()}; unusable
     * numbers are dropped and an exception is thrown only if none remain.
     *
     * @param string|iterable<string> $to Phone number(s) in any common format.
     *
     * @throws SmsException when the gateway rejects the message or is unreachable.
     */
    public function send(string|iterable $to, string|SmsMessage $message): SmsResult;

    /**
     * Remaining credit (number of messages) on the account, or null if the
     * driver has no notion of balance.
     */
    public function balance(): ?int;

    /**
     * Delivery status for a message that was sent with a reference.
     * One of: Delivered, Undelivered, Expired, Pending, Unknown.
     */
    public function status(string $destination, string $reference): ?string;
}
