<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cached totals for the orders list widgets.
 *
 * The cache key includes a version. Writes that can change a total bump that
 * version after the surrounding transaction commits, so the next page load
 * rebuilds once and every later load reads the stored numbers.
 */
class OrderListStats
{
    public const VERSION_KEY = 'orders.list_stats.version';

    /**
     * Fields on orders that the list stats actually read. A comment, attachment,
     * or archive timestamp must not throw the totals away.
     */
    private const ORDER_FIELDS = [
        'status',
        'client_id',
        'author',
        'paid',
        'order_type',
        'product_type',
        'currency_rate',
    ];

    private bool $invalidationQueued = false;

    public static function orderAffectsTotals(Order $order): bool
    {
        return $order->wasRecentlyCreated || $order->wasChanged(self::ORDER_FIELDS);
    }

    /**
     * Payments with no order do not move the order-list totals. A payment that
     * was just unlinked still does, because the order it left is now paid less.
     */
    public static function paymentAffectsTotals(Payment $payment): bool
    {
        return $payment->order_id !== null || $payment->getOriginal('order_id') !== null;
    }

    /**
     * Forget the cached totals. Repeated calls in the same request, including
     * every piece saved while an order form is stored, bump the version once.
     */
    public function invalidate(): void
    {
        if ($this->invalidationQueued) {
            return;
        }

        $this->invalidationQueued = true;

        $bump = function () {
            $this->invalidationQueued = false;

            if (Cache::increment(self::VERSION_KEY) === false) {
                Cache::forever(self::VERSION_KEY, 2);
            }
        };

        // afterCommit waits until the order, its pieces, and its payments are
        // all visible. Bumping in the middle of that transaction would let
        // another request cache the half-saved order.
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($bump);
        } else {
            $bump();
        }
    }

    /**
     * @param  callable(): array  $compute
     * @return array{ordersCount: int, totalPriceGel: float, totalExpenses: float, totalPaid: float, totalUnpaid: float}
     */
    public function remember(string $signature, callable $compute): array
    {
        $key = $this->cacheKey($signature);
        $cached = $this->read($key);

        if ($cached !== null) {
            return $cached;
        }

        try {
            return Cache::lock($key.'.lock', 30)->block(25, function () use ($key, $compute) {
                $cached = $this->read($key);

                if ($cached !== null) {
                    return $cached;
                }

                $stats = $compute();
                Cache::put($key, $stats, now()->addHours(6));

                return $stats;
            });
        } catch (LockTimeoutException) {
            return $this->read($key) ?? $compute();
        }
    }

    public function cacheKey(string $signature): string
    {
        $version = Cache::get(self::VERSION_KEY);
        $version = is_numeric($version) ? (int) $version : 1;

        return 'orders.list_stats.'.$version.'.'.sha1($signature);
    }

    private function read(string $key): ?array
    {
        $cached = Cache::get($key);

        if (!is_array($cached) || !array_key_exists('ordersCount', $cached)) {
            return null;
        }

        return $cached;
    }
}
