<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsException;
use App\Services\Sms\SmsMessage;
use App\Services\Sms\SmsSender;
use Illuminate\Console\Command;

class SmsSend extends Command
{
    protected $signature = 'sms:send
                            {to : Phone number(s), comma separated}
                            {message : Text to send}
                            {--sender= : Override the configured sender title}
                            {--reference= : Your reference id (max 20 chars)}
                            {--not-urgent : Respect recipient opt-outs}';

    protected $description = 'Send an SMS through the configured driver (smsoffice.ge)';

    public function handle(SmsSender $sms): int
    {
        $message = SmsMessage::create((string) $this->argument('message'))
            ->sender($this->option('sender') ?: null)
            ->reference($this->option('reference') ?: null);

        if ($this->option('not-urgent')) {
            $message->urgent(false);
        }

        try {
            $result = $sms->send((string) $this->argument('to'), $message);
        } catch (SmsException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '[%s] Sent to %s%s',
            config('sms.driver'),
            implode(', ', $result->destinations),
            $result->gatewayMessage !== '' ? ' - '.$result->gatewayMessage : ''
        ));

        return self::SUCCESS;
    }
}
