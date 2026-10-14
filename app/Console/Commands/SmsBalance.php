<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsException;
use App\Services\Sms\SmsSender;
use Illuminate\Console\Command;

class SmsBalance extends Command
{
    protected $signature = 'sms:balance';

    protected $description = 'Show the remaining SMS credit on the smsoffice.ge account';

    public function handle(SmsSender $sms): int
    {
        try {
            $balance = $sms->balance();
        } catch (SmsException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($balance === null) {
            $this->warn(sprintf('Driver "%s" does not report a balance.', config('sms.driver')));

            return self::SUCCESS;
        }

        $this->info("Remaining SMS credit: {$balance}");

        return self::SUCCESS;
    }
}
