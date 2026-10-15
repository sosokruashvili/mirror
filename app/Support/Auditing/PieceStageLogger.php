<?php

namespace App\Support\Auditing;

use App\Models\Order;
use App\Models\Piece;
use App\Models\PieceStageLog;
use App\Models\Stage;
use Illuminate\Support\Facades\Log;

/**
 * Records piece production-stage changes into piece_stage_logs.
 *
 * Called from Piece when a stage is actually attached or detached, and from
 * Order for service orders (which track stages on the order, piece_id NULL).
 * Failures are swallowed so logging never blocks the workshop action.
 */
class PieceStageLogger
{
    use ResolvesCauser;

    public function record(Piece $piece, Stage $stage, string $action): void
    {
        try {
            $piece->loadMissing('order.client');
            $causer = $this->resolveCauser();
            $order = $piece->order;

            PieceStageLog::create([
                'piece_id' => $piece->getKey(),
                'order_id' => $piece->order_id ?? $order?->getKey(),
                'client_id' => $order?->client_id,
                'client_name' => $order?->client?->name,
                'stage_id' => $stage->getKey(),
                'user_id' => $causer?->getKey(),
                'user_name' => $this->causerName($causer),
                'action' => $action,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Piece stage logging failed: ' . $e->getMessage(), [
                'action' => $action,
                'piece' => $piece->getKey(),
                'stage' => $stage->getKey(),
            ]);
        }
    }

    /**
     * Record a stage change of a service order (no pieces: the order itself
     * goes through the stages, so the row carries no piece_id).
     */
    public function recordForOrder(Order $order, Stage $stage, string $action): void
    {
        try {
            $order->loadMissing('client');
            $causer = $this->resolveCauser();

            PieceStageLog::create([
                'piece_id' => null,
                'order_id' => $order->getKey(),
                'client_id' => $order->client_id,
                'client_name' => $order->client?->name,
                'stage_id' => $stage->getKey(),
                'user_id' => $causer?->getKey(),
                'user_name' => $this->causerName($causer),
                'action' => $action,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Order stage logging failed: ' . $e->getMessage(), [
                'action' => $action,
                'order' => $order->getKey(),
                'stage' => $stage->getKey(),
            ]);
        }
    }

    /**
     * @param  iterable<int, mixed>  $stages
     */
    public function recordMany(Piece $piece, iterable $stages, string $action): void
    {
        foreach ($stages as $stage) {
            if ($stage instanceof Stage) {
                $this->record($piece, $stage, $action);
            }
        }
    }
}