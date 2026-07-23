<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds and stores the daily per-product warehouse stock snapshot.
 *
 * Each day is a full "as of that date" recomputation (no running deltas), so a
 * snapshot never drifts from the underlying data: it always reflects the exact
 * set of warehouse rows and confirmed orders that existed up to the snapshot
 * date. The live list-page table is replaced by these stored snapshots.
 */
class WarehouseSnapshotService
{
    /**
     * Compute every product's warehouse position as of the end of $date.
     *
     * Mirrors the previous live calculation (WarehouseCrudController::getRemainingStock)
     * but bounded by date: only warehouse stock added on/before $date counts, and
     * only orders that were confirmed on/before $date consume it.
     *
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public function calculateAsOf(Carbon $date): Collection
    {
        $end = $date->copy()->endOfDay();

        // Total warehouse area per product, counting only stock added by the date.
        $warehouseAreas = Warehouse::query()
            ->where('created_at', '<=', $end)
            ->select('product_id', DB::raw('SUM(area) as total_area'))
            ->groupBy('product_id')
            ->pluck('total_area', 'product_id');

        // Orders whose expense counts as of the date: non-draft, and confirmed
        // on/before the date. Legacy orders with no confirm_date fall back to
        // their creation date so historical data is still counted.
        $orderExpenses = Order::query()
            ->where('status', '!=', 'draft')
            ->where(function ($query) use ($end) {
                $query->where('confirm_date', '<=', $end)
                    ->orWhere(function ($fallback) use ($end) {
                        $fallback->whereNull('confirm_date')
                            ->where('created_at', '<=', $end);
                    });
            })
            ->pluck('expenses', 'id');

        // Total expenses per product, counting each order once even if a product
        // appears multiple times on the same order.
        $expensesByProduct = [];
        if ($orderExpenses->isNotEmpty()) {
            DB::table('order_product')
                ->whereIn('order_id', $orderExpenses->keys())
                ->select('order_id', 'product_id')
                ->distinct()
                ->get()
                ->each(function ($row) use (&$expensesByProduct, $orderExpenses) {
                    $expensesByProduct[$row->product_id] =
                        ($expensesByProduct[$row->product_id] ?? 0) + (float) ($orderExpenses[$row->order_id] ?? 0);
                });
        }

        return Product::query()
            ->orderBy('title')
            ->get()
            ->map(function (Product $product) use ($warehouseAreas, $expensesByProduct) {
                $warehouseArea = (float) ($warehouseAreas[$product->id] ?? 0);
                $expenses = (float) ($expensesByProduct[$product->id] ?? 0);
                $offcutPercent = (float) ($product->offcut ?? 0);
                // Expenses already include offcut, so peel the offcut portion back out:
                // expenses = base * (1 + pct/100)  →  offcut = expenses * pct/(100+pct)
                $offcutArea = ($offcutPercent > 0 && $expenses > 0)
                    ? round($expenses * $offcutPercent / (100 + $offcutPercent), 3)
                    : 0.0;

                return (object) [
                    'id' => $product->id,
                    'title' => $product->title,
                    'offcut' => $offcutPercent,
                    'offcut_area' => $offcutArea,
                    'warehouse_area' => $warehouseArea,
                    'expenses' => $expenses,
                    'remaining' => $warehouseArea - $expenses,
                ];
            });
    }

    /**
     * Snapshot every product's warehouse stock for the given date.
     * Re-running for the same date overwrites that date's snapshot.
     *
     * @return int Number of products snapshotted.
     */
    public function snapshotDailyStock(?Carbon $date = null): int
    {
        $date = ($date ?? now())->copy()->startOfDay();

        $rows = $this->calculateAsOf($date);
        $count = 0;

        foreach ($rows as $row) {
            WarehouseSnapshot::updateOrCreate(
                [
                    'product_id' => $row->id,
                    'snapshot_date' => $date->toDateString(),
                ],
                [
                    'warehouse_area' => round($row->warehouse_area, 3),
                    'expenses' => round($row->expenses, 3),
                    'remaining' => round($row->remaining, 3),
                    'offcut_percent' => round($row->offcut, 2),
                    'offcut_area' => round($row->offcut_area, 3),
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Distinct snapshot dates (Y-m-d strings), newest first — for the date picker.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function availableSnapshotDates(): Collection
    {
        return WarehouseSnapshot::query()
            ->select('snapshot_date')
            ->distinct()
            ->orderBy('snapshot_date', 'desc')
            ->pluck('snapshot_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->values();
    }

    /**
     * Stored snapshot rows for a date, shaped like calculateAsOf() so the same
     * view can render either source.
     *
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public function rowsForDate(string $date): Collection
    {
        return WarehouseSnapshot::query()
            ->with('product:id,title')
            ->where('snapshot_date', $date)
            ->get()
            ->map(function (WarehouseSnapshot $snapshot) {
                return (object) [
                    'id' => $snapshot->product_id,
                    'title' => $snapshot->product?->title ?? ('#' . $snapshot->product_id),
                    'offcut' => (float) $snapshot->offcut_percent,
                    'offcut_area' => (float) $snapshot->offcut_area,
                    'warehouse_area' => (float) $snapshot->warehouse_area,
                    'expenses' => (float) $snapshot->expenses,
                    'remaining' => (float) $snapshot->remaining,
                ];
            })
            ->sortBy('title')
            ->values();
    }
}
