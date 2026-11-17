<?php

namespace App\Support\Auditing;

use App\Models\BrokenGlass;
use App\Models\Piece;
use App\Support\BrokenGlass\BreakDetails;

/**
 * Records broken-glass events into broken_glasses.
 *
 * Unlike PieceStageLogger this does NOT swallow failures: the break is the user's
 * action, not a side effect of it, and TeamOrderController reports the resulting
 * count back to the workshop UI, so a failed write has to surface.
 *
 * $quantity is how many pieces the break sent back through production (a size-group
 * break records one row for the whole group). It is reporting-only — see BrokenGlass.
 *
 * $details are the already-validated dialog fields (BreakDetails::validate()):
 * description, machine_id, detected_stage_id, usable_area. damaged_area is always
 * the area of ONE sheet of the piece, matching the one extra sheet the order is
 * charged for the break.
 */
class BrokenGlassLogger
{
    use ResolvesCauser;

    /**
     * @param  array{description?: ?string, machine_id?: ?int, detected_stage_id?: ?int, usable_area?: ?float}  $details
     */
    public function record(Piece $piece, array $details = [], int $quantity = 1): BrokenGlass
    {
        $piece->loadMissing('order.client');
        $causer = $this->resolveCauser();
        $order = $piece->order;

        return BrokenGlass::create([
            'piece_id' => $piece->getKey(),
            'description' => $details['description'] ?? null,
            'quantity' => max(1, $quantity),
            'user_id' => $causer?->getKey(),
            'user_name' => $this->causerName($causer),
            'order_id' => $piece->order_id ?? $order?->getKey(),
            'client_id' => $order?->client_id,
            'client_name' => $order?->client?->name,
            'machine_id' => $details['machine_id'] ?? null,
            'detected_stage_id' => $details['detected_stage_id'] ?? null,
            'damaged_area' => BreakDetails::sheetArea($piece->width, $piece->height),
            'usable_area' => $details['usable_area'] ?? null,
        ]);
    }
}
