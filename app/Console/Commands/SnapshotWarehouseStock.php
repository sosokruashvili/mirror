<?php

namespace App\Console\Commands;

use App\Services\WarehouseSnapshotService;
use Illuminate\Console\Command;

class SnapshotWarehouseStock extends Command
{
    protected $signature = 'warehouse:snapshot-daily
                            {--date= : Snapshot a specific date (Y-m-d)}';

    protected $description = 'Snapshot each product\'s warehouse stock (area, expenses, remaining) for a given day';

    public function handle(WarehouseSnapshotService $service): int
    {
        $date = $this->option('date')
            ? \Carbon\Carbon::parse($this->option('date'))->startOfDay()
            : now()->startOfDay();

        $count = $service->snapshotDailyStock($date);

        $this->info(sprintf(
            'Snapshotted warehouse stock for %d product(s) on %s.',
            $count,
            $date->format('Y-m-d')
        ));

        return self::SUCCESS;
    }
}
