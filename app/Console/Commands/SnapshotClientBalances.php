<?php

namespace App\Console\Commands;

use App\Services\ClientBalanceService;
use Illuminate\Console\Command;

class SnapshotClientBalances extends Command
{
    protected $signature = 'clients:snapshot-balances
                            {--date= : Snapshot a specific date (Y-m-d)}';

    protected $description = 'Snapshot every client\'s current balance for a given day';

    public function handle(ClientBalanceService $service): int
    {
        $date = $this->option('date')
            ? \Carbon\Carbon::parse($this->option('date'))->startOfDay()
            : now()->startOfDay();

        $count = $service->snapshotDailyBalances($date);

        $this->info(sprintf(
            'Snapshotted balances for %d client(s) on %s.',
            $count,
            $date->format('Y-m-d')
        ));

        return self::SUCCESS;
    }
}
