<?php

namespace App\Observers;

use App\Models\Piece;
use App\Services\OrderPieceStatusSync;

class PieceObserver
{
    public function updated(Piece $piece): void
    {
        if (!$piece->wasChanged('status') || !$piece->order) {
            return;
        }

        $order = $piece->order;

        $order->expenses = $order->calculateExpenses();
        $order->save();

        if (!OrderPieceStatusSync::isSyncingFromOrder()) {
            OrderPieceStatusSync::syncOrderStatusFromPieces($order);
        }
    }
}
