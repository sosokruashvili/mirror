<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS driver
    |--------------------------------------------------------------------------
    |
    | "smsoffice" sends real messages through smsoffice.ge. "log" writes the
    | message to the Laravel log instead (use it in local/dev so nothing is
    | billed), "array" keeps messages in memory for tests.
    |
    */

    'driver' => env('SMS_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | smsoffice.ge
    |--------------------------------------------------------------------------
    |
    | key    - secret API key from the smsoffice.ge profile page.
    | sender - approved sender title (max 11 chars: A-Z a-z 0-9 "-" ".").
    | urgent - send even to numbers that opted out of this sender; smsoffice
    |          recommends true for transactional messages.
    |
    */

    'smsoffice' => [
        'key' => env('SMS_OFFICE_KEY'),
        'sender' => env('SMS_OFFICE_SENDER'),
        'urgent' => (bool) env('SMS_OFFICE_URGENT', true),
        'base_url' => rtrim((string) env('SMS_OFFICE_URL', 'https://smsoffice.ge'), '/'),
        'timeout' => (int) env('SMS_OFFICE_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Phone numbers
    |--------------------------------------------------------------------------
    |
    | Numbers are normalised to international format without "+" or "00"
    | (e.g. 995577123456). Local numbers of `local_length` digits get the
    | `default_country_code` prepended.
    |
    */

    'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '995'),
    'local_length' => 9,

];
