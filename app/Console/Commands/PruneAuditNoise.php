<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Deletes audit_logs rows that record a change which never actually happened.
 *
 * Until Order::calculateTotalPrice() rounded to the column's scale, every call
 * rewrote each piece's price with full float precision (11.731708479744002)
 * against a numeric(10,2) column holding 11.73. The two never compared equal, so
 * Eloquent saw a dirty model on every read-heavy page and logged an update whose
 * old and new values are the same number.
 *
 * Those rows are pure noise: this command finds updates whose diff is a single
 * `price` key that is unchanged once both sides are rounded to 2dp, and removes
 * them. Anything with a real difference is left alone.
 */
class PruneAuditNoise extends Command
{
    protected $signature = 'audit:prune-noise
                            {--dry-run : Report what would be deleted without deleting}
                            {--chunk=2000 : Rows to delete per statement}';

    protected $description = 'Delete audit log rows for price updates that were rounding churn, not real changes';

    public function handle(): int
    {
        $ids = $this->collectNoiseIds();

        $this->newLine();
        $this->line('Total audit rows:  ' . number_format(AuditLog::count()));
        $this->line('Rounding churn:    ' . number_format(count($ids)));

        if (empty($ids)) {
            $this->info('Nothing to prune.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — nothing deleted.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach (array_chunk($ids, (int) $this->option('chunk')) as $chunk) {
            $deleted += AuditLog::whereIn('id', $chunk)->delete();
        }

        $this->info('Deleted ' . number_format($deleted) . ' rows.');
        $this->line('Remaining:         ' . number_format(AuditLog::count()));

        return self::SUCCESS;
    }

    /**
     * Ids of update rows whose only changed field is a price that is identical
     * once both sides are rounded to the stored scale.
     *
     * The comparison is done in PHP rather than SQL because the values are JSON
     * and arrive as a mix of strings ("11.73") and floats (11.7317...).
     */
    protected function collectNoiseIds(): array
    {
        $ids = [];

        AuditLog::query()
            ->where('event', 'updated')
            ->select('id', 'old_values', 'new_values')
            ->orderBy('id')
            ->chunk(5000, function ($rows) use (&$ids) {
                foreach ($rows as $row) {
                    if ($this->isRoundingChurn($row)) {
                        $ids[] = $row->id;
                    }
                }
            });

        return $ids;
    }

    protected function isRoundingChurn(AuditLog $row): bool
    {
        $old = $row->old_values ?? [];
        $new = $row->new_values ?? [];

        // Only ever touched price, and had a previous value to compare against.
        if (array_keys($old) !== ['price'] || array_keys($new) !== ['price']) {
            return false;
        }

        if ($old['price'] === null || $new['price'] === null) {
            return false;
        }

        if (! is_numeric($old['price']) || ! is_numeric($new['price'])) {
            return false;
        }

        return round((float) $old['price'], 2) === round((float) $new['price'], 2);
    }
}
