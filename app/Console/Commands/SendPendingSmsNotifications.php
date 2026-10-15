<?php

namespace App\Console\Commands;

use App\Services\Notifications\SmsNotificationDispatcher;
use Illuminate\Console\Command;

class SendPendingSmsNotifications extends Command
{
    protected $signature = 'notifications:send
                            {--limit=20 : How many pending SMS rows to send this run}';

    protected $description = 'Send pending SMS notifications';

    public function handle(SmsNotificationDispatcher $dispatcher): int
    {
        $result = $dispatcher->dispatch(max(1, (int) $this->option('limit')));

        if ($result['sent'] === 0 && $result['failed'] === 0 && $result['retried'] === 0) {
            $this->info('No pending SMS notifications.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'SMS notifications: %d sent, %d waiting to retry, %d failed.',
            $result['sent'],
            $result['retried'],
            $result['failed']
        ));

        return self::SUCCESS;
    }
}
