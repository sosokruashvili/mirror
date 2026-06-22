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
     * Production pieces exclude draft entries that are not yet confirmed.
     */
    public static function productionPieces(Order $order)
    {
        if (!$order->relationLoaded('pieces')) {
            $order->load('pieces');
        }

        return $order->pieces->reject(fn ($piece) => $piece->status === 'draft');
    }

    /**
     * Derive the order status that matches the current piece statuses.
     *
     * @return string|null Target status, or null when no rule applies.
     */
    public static function determineOrderStatus(Order $order): ?string
    {
        $pieces = self::productionPieces($order);

        if ($pieces->isEmpty()) {
            return null;
        }

        if ($pieces->every(fn ($piece) => $piece->status === 'finished')) {
            return 'finished';
        }

        if ($pieces->every(fn ($piece) => $piece->status === 'ready')) {
            return 'ready';
        }

        if ($pieces->contains(fn ($piece) => in_array($piece->status, ['cut', 'processed'], true))) {
            return 'working';
        }

        if (
            $pieces->contains(fn ($piece) => $piece->status === 'ready')
            && $pieces->contains(fn ($piece) => $piece->status === 'new')
        ) {
            return 'working';
        }

        return null;
    }

    /**
     * Update order status from related piece statuses when a rule matches.
     */
    public static function syncOrderStatusFromPieces(Order $order, bool $dryRun = false): bool
    {
        if ($order->status === 'draft') {
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

        $query = Order::with('pieces')->orderBy('id');

        if ($orderId !== null) {
            $query->whereKey($orderId);
        }

        $query->chunkById(100, function ($orders) use ($dryRun, &$stats) {
            foreach ($orders as $order) {
                $stats['processed']++;

                $fromStatus = $order->status;
                $targetStatus = $order->status === 'draft'
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
