<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Stage;

class OrderPieceStatusSync
{
    /**
     * Slug of the stage a piece lands on once its order is handed out.
     */
    public const FINISHED_STAGE = 'finished';

    /**
     * Slug of the final production stage (დასრულება). Its position is the
     * gate for handing an order out: see piecesReadyForFinish().
     */
    public const COMPLETION_STAGE = 'completion';

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

        if ($pieces->every(fn ($piece) => $piece->stages->contains('name', self::COMPLETION_STAGE))) {
            return 'ready';
        }

        if ($pieces->contains(fn ($piece) => $piece->stages->isNotEmpty())) {
            return 'working';
        }

        return null;
    }

    /**
     * Whether the order has been produced far enough to be handed out: every
     * piece must have completed a stage at or past the დასრულება stage's
     * position. An order whose pieces never went through production fails
     * this check, so a stray "გატანილია" tap on the team page cannot finish
     * it. Pieceless (service-only) orders pass, and so does everything when
     * the დასრულება stage is missing (there is no gate to measure against).
     */
    public static function piecesReadyForFinish(Order $order): bool
    {
        $gate = Stage::ordered()->firstWhere('name', self::COMPLETION_STAGE)?->position;

        if ($gate === null) {
            return true;
        }

        return self::productionPieces($order)->every(
            fn ($piece) => $piece->stages->contains(fn ($stage) => $stage->position >= $gate)
        );
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
     * Move every piece of a handed-out order onto the გატანილია stage.
     *
     * Handing an order out is a whole-order action, so each of its pieces must
     * end up on the matching final stage — otherwise the pieces keep showing
     * the stage they were left on. Only the 'finished' stage is recorded here:
     * earlier stages stay as they are so the piece_stage pivot keeps crediting
     * the people who actually did that work (the user and service stats read
     * it). Completing this last stage still auto-closes 'completion' for pieces
     * whose production stages are all done.
     *
     * The piece→order direction is suppressed while running: the order status
     * is the input here, so it must not be recalculated from the pieces.
     *
     * @return int Number of pieces moved onto the stage.
     */
    public static function markPiecesFinished(Order $order): int
    {
        $stage = Stage::ordered()->firstWhere('name', self::FINISHED_STAGE);

        if ($stage === null) {
            return 0;
        }

        return self::withoutPieceToOrderSync(function () use ($order, $stage) {
            $updated = 0;

            foreach (self::productionPieces($order) as $piece) {
                if ($piece->stages->contains('id', $stage->id)) {
                    continue;
                }

                $piece->setStageCompleted($stage, true);
                $updated++;
            }

            return $updated;
        });
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
