<?php

namespace App\Providers;

use App\Models\BrokenGlass;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Piece;
use App\Models\Product;
use App\Services\OrderListStats;
use Illuminate\Support\ServiceProvider;

/**
 * Drops the orders-list totals cache when a write would change those totals.
 */
class OrderListStatsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrderListStats::class);
    }

    public function boot(): void
    {
        $invalidate = function () {
            $this->app->make(OrderListStats::class)->invalidate();
        };

        Order::saved(function (Order $order) use ($invalidate) {
            if (OrderListStats::orderAffectsTotals($order)) {
                $invalidate();
            }
        });
        Order::deleted($invalidate);

        // Piece size, quantity, and break count are what the expense and the
        // glass price are calculated from. Stage checkboxes do not save the piece.
        Piece::saved($invalidate);
        Piece::deleted($invalidate);

        BrokenGlass::saved($invalidate);
        BrokenGlass::deleted($invalidate);

        Payment::saved(function (Payment $payment) use ($invalidate) {
            if (OrderListStats::paymentAffectsTotals($payment)) {
                $invalidate();
            }
        });
        Payment::deleted(function (Payment $payment) use ($invalidate) {
            if (OrderListStats::paymentAffectsTotals($payment)) {
                $invalidate();
            }
        });

        // Pivot price wins when it is set. Catalog price and offcut still feed
        // orders that have no pivot price, and every order's offcut.
        Product::saved(function (Product $product) use ($invalidate) {
            if ($product->wasChanged(['price', 'offcut'])) {
                $invalidate();
            }
        });
    }
}
