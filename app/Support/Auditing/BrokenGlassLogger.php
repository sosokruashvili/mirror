<?php

namespace App\Support\Auditing;

use App\Models\BrokenGlass;
use App\Models\Piece;

/**
 * Records broken-glass events into broken_glasses.
 *
 * Unlike PieceStageLogger this does NOT swallow failures: the break is the user's
 * action, not a side effect of it, and TeamOrderController reports the resulting
 * count back to the workshop UI, so a failed write has to surface.
 *
 * $quantity is how many pieces the break sent back through production (a size-group
 * break records one row for the whole group). It is reporting-only — see BrokenGlass.
 */
class BrokenGlassLogger
{
    use ResolvesCauser;

    public function record(Piece $piece, ?string $description, int $quantity = 1): BrokenGlass
    {
        $piece->loadMissing('order.client');
        $causer = $this->resolveCauser();
        $order = $piece->order;

        return BrokenGlass::create([
            'piece_id' => $piece->getKey(),
            'description' => $description,
            'quantity' => max(1, $quantity),
            'user_id' => $causer?->getKey(),
            'user_name' => $this->causerName($causer),
            'order_id' => $piece->order_id ?? $order?->getKey(),
            'client_id' => $order?->client_id,
            'client_name' => $order?->client?->name,
        ]);
    }
}
