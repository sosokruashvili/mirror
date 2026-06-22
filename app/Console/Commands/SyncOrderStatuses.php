<?php

namespace App\Console\Commands;

use App\Services\OrderPieceStatusSync;
use Illuminate\Console\Command;

class SyncOrderStatuses extends Command
{
    protected $signature = 'orders:sync-statuses
                            {--dry-run : Preview changes without saving}
                            {--order= : Sync a single order by ID}';

    protected $description = 'Resync order statuses from piece statuses using OrderPieceStatusSync rules';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $orderId = $this->option('order');
        $orderId = $orderId !== null ? (int) $orderId : null;

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
        }

        if ($orderId !== null) {
            $this->info("Syncing order #{$orderId}...");
        } else {
            $this->info('Syncing all orders...');
        }

        $stats = OrderPieceStatusSync::resyncAllOrders($dryRun, $orderId);

        if ($orderId !== null && $stats['processed'] === 0) {
            $this->error("Order #{$orderId} not found.");

            return self::FAILURE;
        }

        foreach ($stats['changes'] as $change) {
            $this->line("Order #{$change['id']}: {$change['from']} → {$change['to']}");
        }

        $this->newLine();
        $this->info("Processed: {$stats['processed']}");
        $this->info(($dryRun ? 'Would update' : 'Updated') . ": {$stats['updated']}");
        $this->info("Skipped (already correct or no rule): {$stats['skipped']}");

        return self::SUCCESS;
    }
}
