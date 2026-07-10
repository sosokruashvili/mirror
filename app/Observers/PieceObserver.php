<?php

namespace App\Observers;

use App\Models\Piece;
use App\Services\OrderPieceStatusSync;

class PieceObserver
{
    public function updated(Piece $piece): void
    {
        if (!$piece->wasChanged('stage') || !$piece->order) {
            return;
        }

        if (!OrderPieceStatusSync::isSyncingFromOrder()) {
            OrderPieceStatusSync::syncOrderStatusFromPieces($piece->order);
        }
    }
}
