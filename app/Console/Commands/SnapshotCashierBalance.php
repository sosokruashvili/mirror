<?php

namespace App\Console\Commands;

use App\Services\CashierService;
use Illuminate\Console\Command;

class SnapshotCashierBalance extends Command
{
    protected $signature = 'cashier:snapshot-daily
                            {--date= : Snapshot a specific date (Y-m-d)}';

    protected $description = 'Snapshot the cashier closing balance for a given day';

    public function handle(CashierService $cashierService): int
    {
        $date = $this->option('date')
            ? \Carbon\Carbon::parse($this->option('date'))->startOfDay()
            : now()->startOfDay();

        $balance = $cashierService->snapshotDailyBalance($date);

        $this->info(sprintf(
            'Cashier balance for %s: %s ₾',
            $balance->balance_date->format('Y-m-d'),
            number_format($balance->amount, 2)
        ));

        return self::SUCCESS;
    }
}
