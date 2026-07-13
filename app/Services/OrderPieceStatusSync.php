<?php

namespace App\Services;

use App\Models\Order;

class OrderPieceStatusSync
{
    private static bool $syncingFromOrder = false;

    public static function isSyncingFromOrder(): bool
    {
        return self::$syncingFromOrder;
    }

    /**
     * Run a callback without triggering piece→order status sync.
     * Use when piece statuses are updated as a result of an order status change.
     */
    public static function withoutPieceToOrderSync(callable $callback): mixed
    {
        self::$syncingFromOrder = true;

        try {
            return $callback();
        } finally {
            self::$syncingFromOrder = false;
        }
    }

    /**
     * Production pieces of a (non-draft) order. Draft orders never sync, so all
     * pieces of the order are considered production pieces here.
     */
    public static function productionPieces(Order $order)
    {
        // Completed stages live in the piece_stage pivot, so make sure they are
        // loaded for the status rules below.
        $order->loadMissing('pieces.stages');

        return $order->pieces;
    }

    /**
     * Derive the order status that matches the current piece production stages.
     *
     * Stage-driven rules:
     *  - every piece at the final stage ('completion') → order 'ready'
     *  - any piece with a stage set (production started) → order 'working'
     *  - otherwise no rule applies (order status left untouched)
     *
     * 'finished' is never derived here — it's set explicitly when an order is
     * handed out (გატანილია).
     *
     * @return string|null Target status, or null when no rule applies.
     */
    public static function determineOrderStatus(Order $order): ?string
    {
        $pieces = self::productionPieces($order);

        if ($pieces->isEmpty()) {
            return null;
        }

        if ($pieces->every(fn ($piece) => $piece->stages->contains('name', 'completion'))) {
            return 'ready';
        }

        if ($pieces->contains(fn ($piece) => $piece->stages->isNotEmpty())) {
            return 'working';
        }

        return null;
    }

    /**
     * Update order status from related piece stages when a rule matches.
     */
    public static function syncOrderStatusFromPieces(Order $order, bool $dryRun = false): bool
    {
        // Draft orders aren't in production yet; finished orders are handed out
        // and must not be downgraded by stage changes.
        if (in_array($order->status, ['draft', 'finished'], true)) {
            return false;
        }

        $targetStatus = self::determineOrderStatus($order);

        if ($targetStatus === null || $order->status === $targetStatus) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        $order->status = $targetStatus;
        $order->save();

        return true;
    }

    /**
     * Resync order statuses for all non-draft orders (or a single order).
     *
     * @return array{processed: int, updated: int, skipped: int, changes: array<int, array{id: int, from: string, to: string}>}
     */
    public static function resyncAllOrders(bool $dryRun = false, ?int $orderId = null): array
    {
        $stats = [
            'processed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'changes' => [],
        ];

        $query = Order::with('pieces.stages')->orderBy('id');

        if ($orderId !== null) {
            $query->whereKey($orderId);
        }

        $query->chunkById(100, function ($orders) use ($dryRun, &$stats) {
            foreach ($orders as $order) {
                $stats['processed']++;

                $fromStatus = $order->status;
                $targetStatus = in_array($order->status, ['draft', 'finished'], true)
                    ? null
                    : self::determineOrderStatus($order);

                if ($targetStatus === null || $fromStatus === $targetStatus) {
                    $stats['skipped']++;
                    continue;
                }

                if (self::syncOrderStatusFromPieces($order, $dryRun)) {
                    $stats['updated']++;
                    $stats['changes'][] = [
                        'id' => $order->id,
                        'from' => $fromStatus,
                        'to' => $targetStatus,
                    ];
                }
            }
        });

        return $stats;
    }
}
