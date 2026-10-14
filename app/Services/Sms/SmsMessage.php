<?php

namespace App\Services\Sms;

use DateTimeInterface;

/**
 * The text and sending options of a single SMS (independent of recipients).
 */
final class SmsMessage
{
    public ?string $sender = null;

    public ?bool $urgent = null;

    public ?string $reference = null;

    public ?DateTimeInterface $scheduledAt = null;

    public function __construct(public string $content)
    {
    }

    public static function create(string $content): self
    {
        return new self($content);
    }

    public static function from(string|self $message): self
    {
        return $message instanceof self ? $message : new self($message);
    }

    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /** Override the configured sender title. */
    public function sender(?string $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    /** Deliver even if the recipient opted out of this sender (transactional). */
    public function urgent(bool $urgent = true): self
    {
        $this->urgent = $urgent;

        return $this;
    }

    /**
     * Your own id for the message (max 20 chars). Required if you want to
     * query delivery status for it later.
     */
    public function reference(?string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function scheduledAt(?DateTimeInterface $at): self
    {
        $this->scheduledAt = $at;

        return $this;
    }
}
