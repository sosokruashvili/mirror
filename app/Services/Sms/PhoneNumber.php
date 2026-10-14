<?php

namespace App\Services\Sms;

final class PhoneNumber
{
    /**
     * Normalise a phone number into the "995577123456" form the gateway
     * expects, or return null when it cannot be a valid destination.
     *
     * Accepts "+995 577 12 34 56", "00995577123456", "577123456", "577 12-34-56".
     */
    public static function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $localLength = (int) config('sms.local_length', 9);
        $countryCode = (string) config('sms.default_country_code', '995');

        if ($localLength > 0 && strlen($digits) === $localLength && $countryCode !== '') {
            $digits = $countryCode.$digits;
        }

        // E.164 allows up to 15 digits; anything shorter than a country code
        // plus a real subscriber number is noise.
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    /**
     * Normalise a list of numbers, dropping blanks, invalid entries and duplicates.
     *
     * @param string|iterable<string|null> $numbers
     * @return list<string>
     */
    public static function normalizeMany(string|iterable $numbers): array
    {
        if (is_string($numbers)) {
            $numbers = preg_split('/[,;\n]+/', $numbers) ?: [];
        }

        $result = [];

        foreach ($numbers as $number) {
            $normalized = self::normalize($number);

            // Keep values as strings: numeric-looking array keys would be cast to int.
            if ($normalized !== null && ! in_array($normalized, $result, true)) {
                $result[] = $normalized;
            }
        }

        return $result;
    }

    /**
     * Render the raw input for error messages.
     *
     * @param string|iterable<string|null> $numbers
     */
    public static function describe(string|iterable $numbers): string
    {
        if (is_string($numbers)) {
            return $numbers;
        }

        $parts = [];

        foreach ($numbers as $number) {
            $parts[] = (string) $number;
        }

        return implode(',', $parts);
    }
}
