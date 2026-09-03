<?php

namespace App\Support\Auditing;

use App\Models\Piece;
use App\Models\PieceStageLog;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Records piece production-stage changes into piece_stage_logs.
 *
 * Called from Piece when a stage is actually attached or detached. Failures
 * are swallowed so logging never blocks the workshop action.
 */
class PieceStageLogger
{
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

    protected function resolveCauser(): ?Model
    {
        if (function_exists('backpack_user') && ($user = backpack_user())) {
            return $user;
        }

        return auth()->user();
    }

    protected function causerName(?Model $causer): ?string
    {
        if (! $causer) {
            return null;
        }

        return $causer->name
            ?? $causer->email
            ?? (class_basename($causer) . ' #' . $causer->getKey());
    }
}