<?php

namespace App\Services\Sms;

use RuntimeException;
use Throwable;

class SmsException extends RuntimeException
{
    /** smsoffice.ge "Temporary delay" - safe to retry later. */
    public const TEMPORARY_DELAY = -100;

    /**
     * Human readable descriptions of the smsoffice.ge response codes.
     *
     * @var array<int, string>
     */
    public const SMSOFFICE_CODES = [
        0 => 'Accepted',
        10 => 'Destination does not contain any valid numbers',
        20 => 'Balance is insufficient',
        40 => 'Content is too long',
        60 => 'Content parameter is missing',
        70 => 'Destination parameter is empty',
        75 => 'All numbers are stoplisted',
        76 => 'All numbers have invalid format',
        77 => 'All numbers are stoplisted or have invalid format',
        80 => 'API key is incorrect',
        110 => 'Sender parameter is malformed',
        120 => 'API access is disabled (enable it on the smsoffice.ge profile page)',
        150 => 'Sender not found (check the title spelling)',
        165 => 'Sender is not active yet (awaiting approval on smsoffice.ge)',
        500 => 'API key is missing',
        600 => 'Destination is missing',
        700 => 'Sender is missing',
        800 => 'Content is missing',
        -100 => 'Temporary delay',
    ];

    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?array $response = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromGatewayCode(int $code, string $gatewayMessage = '', ?array $response = null): self
    {
        $description = self::SMSOFFICE_CODES[$code] ?? 'Unknown gateway error';
        $detail = $gatewayMessage !== '' && $gatewayMessage !== $description ? " ({$gatewayMessage})" : '';

        return new self("smsoffice.ge: {$description}{$detail} [code {$code}]", $code, null, $response);
    }

    public static function transport(Throwable $previous): self
    {
        return new self('smsoffice.ge request failed: '.$previous->getMessage(), 0, $previous);
    }

    public static function invalidDestination(string $raw): self
    {
        return new self("Invalid SMS destination: \"{$raw}\"");
    }

    public static function notConfigured(string $what): self
    {
        return new self("SMS is not configured: {$what}");
    }

    /** Whether retrying the same request later makes sense. */
    public function isRetryable(): bool
    {
        return $this->getCode() === self::TEMPORARY_DELAY || $this->getPrevious() !== null;
    }
}
