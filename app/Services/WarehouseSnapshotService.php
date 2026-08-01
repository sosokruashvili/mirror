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
     * Source data shared across every date computed by this instance, so that
     * rebuilding a long range of dates stays a handful of queries in total.
     *
     * @var array{warehouse: \Illuminate\Support\Collection, orders: \Illuminate\Support\Collection, products_by_order: \Illuminate\Support\Collection, products: \Illuminate\Support\Collection}|null
     */
    protected ?array $source = null;

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
        $source = $this->source();

        // Total warehouse area per product, counting only stock added by the date.
        $warehouseAreas = $source['warehouse']
            ->filter(fn (array $row) => $row['at'] <= $end)
            ->groupBy('product_id')
            ->map(fn (Collection $rows) => $rows->sum('area'));

        // Orders whose expense counts as of the date: non-draft, and confirmed
        // on/before the date. Legacy orders with no confirm_date fall back to
        // their creation date so historical data is still counted.
        $orderExpenses = $source['orders']
            ->filter(fn (array $row) => $row['at'] <= $end)
            ->pluck('expenses', 'id');

        // Total expenses per product, counting each order once even if a product
        // appears multiple times on the same order.
        $expensesByProduct = [];
        foreach ($orderExpenses as $orderId => $expense) {
            foreach ($source['products_by_order'][$orderId] ?? [] as $productId) {
                $expensesByProduct[$productId] = ($expensesByProduct[$productId] ?? 0) + $expense;
            }
        }

        return $source['products']
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
     * Load (once per instance) every row calculateAsOf() needs, unbounded by date.
     * Each date then filters this in memory, so rebuilding the whole history costs
     * the same four queries as rebuilding a single day.
     *
     * @return array{warehouse: \Illuminate\Support\Collection, orders: \Illuminate\Support\Collection, products_by_order: \Illuminate\Support\Collection, products: \Illuminate\Support\Collection}
     */
    protected function source(): array
    {
        if ($this->source !== null) {
            return $this->source;
        }

        $warehouse = Warehouse::query()
            ->select('product_id', 'area', 'created_at')
            ->get()
            ->map(fn (Warehouse $row) => [
                'product_id' => (int) $row->product_id,
                'area' => (float) $row->area,
                'at' => $row->created_at,
            ])
            ->filter(fn (array $row) => $row['at'] !== null)
            ->values();

        // An order's expense counts from its confirm_date; legacy orders with no
        // confirm_date fall back to their creation date.
        $orders = Order::query()
            ->where('status', '!=', 'draft')
            ->select('id', 'expenses', 'confirm_date', 'created_at')
            ->get()
            ->map(fn (Order $order) => [
                'id' => (int) $order->id,
                'expenses' => (float) $order->expenses,
                'at' => $order->confirm_date ?? $order->created_at,
            ])
            ->filter(fn (array $row) => $row['at'] !== null)
            ->values();

        $productsByOrder = DB::table('order_product')
            ->select('order_id', 'product_id')
            ->distinct()
            ->get()
            ->groupBy('order_id')
            ->map(fn (Collection $rows) => $rows->pluck('product_id')->map(fn ($id) => (int) $id)->all());

        return $this->source = [
            'warehouse' => $warehouse,
            'orders' => $orders,
            'products_by_order' => $productsByOrder,
            'products' => Product::query()->orderBy('title')->get(),
        ];
    }

    /**
     * Forget the cached source data so the next calculation re-reads the database.
     *
     * @return void
     */
    public function refresh(): void
    {
        $this->source = null;
    }

    /**
     * Recompute every snapshot date already stored, plus today.
     *
     * Warehouse rows and orders are edited in place — a correction keeps the row's
     * original created_at/confirm_date, so it retroactively changes every day from
     * that date onward, not just today. Rebuilding only today would leave the older
     * snapshots showing the pre-correction numbers forever, so a manual recalculation
     * replays the full stored history.
     *
     * Dates that have no snapshot yet are left alone (no back-filling of days the
     * system never observed), except for today, which is always (re)generated.
     *
     * @return array{dates: int, products: int}
     */
    public function rebuildStoredSnapshots(?Carbon $from = null): array
    {
        $this->refresh();

        $dates = $this->availableSnapshotDates()
            ->when($from, fn (Collection $all) => $all->filter(
                fn (string $date) => $date >= $from->copy()->startOfDay()->toDateString()
            ))
            ->push(now()->toDateString())
            ->unique()
            ->sort()
            ->values();

        $products = 0;
        foreach ($dates as $date) {
            $products += $this->snapshotDailyStock(Carbon::parse($date));
        }

        return ['dates' => $dates->count(), 'products' => $products];
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
