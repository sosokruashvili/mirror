<?php

namespace App\Console\Commands;

use App\Services\WarehouseSnapshotService;
use Illuminate\Console\Command;

class SnapshotWarehouseStock extends Command
{
    protected $signature = 'warehouse:snapshot-daily
                            {--date= : Snapshot a specific date (Y-m-d)}
                            {--rebuild : Recompute every stored snapshot date (plus today), not just one day}
                            {--from= : With --rebuild, only recompute stored dates on/after this date (Y-m-d)}';

    protected $description = 'Snapshot each product\'s warehouse stock (area, expenses, remaining) for a given day';

    public function handle(WarehouseSnapshotService $service): int
    {
        // Corrections to warehouse rows and orders are retroactive (the row keeps its
        // original date), so --rebuild replays the stored history to pick them up.
        if ($this->option('rebuild')) {
            $from = $this->option('from')
                ? \Carbon\Carbon::parse($this->option('from'))->startOfDay()
                : null;

            $result = $service->rebuildStoredSnapshots($from);

            $this->info(sprintf(
                'Rebuilt %d snapshot date(s), %d product-rows in total.',
                $result['dates'],
                $result['products']
            ));

            return self::SUCCESS;
        }

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
