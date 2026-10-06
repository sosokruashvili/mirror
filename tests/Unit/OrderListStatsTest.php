<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderListStats;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderListStatsTest extends TestCase
{
    public function test_remember_reuses_the_stored_totals(): void
    {
        $stats = $this->app->make(OrderListStats::class);
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return $this->sampleTotals();
        };

        $first = $stats->remember('{}', $compute);
        $second = $stats->remember('{}', $compute);

        $this->assertSame(1, $calls);
        $this->assertSame($first, $second);
    }

    public function test_a_different_filter_is_cached_on_its_own(): void
    {
        $stats = $this->app->make(OrderListStats::class);
        $calls = 0;

        $stats->remember('{}', function () use (&$calls) {
            $calls++;

            return $this->sampleTotals();
        });
        $filtered = $stats->remember('{"status":"new"}', function () use (&$calls) {
            $calls++;

            return ['ordersCount' => 3, 'totalPriceGel' => 10.0, 'totalExpenses' => 1.0, 'totalPaid' => 4.0, 'totalUnpaid' => 6.0];
        });

        $this->assertSame(2, $calls);
        $this->assertSame(3, $filtered['ordersCount']);
    }

    public function test_invalidate_outside_a_transaction_bumps_immediately(): void
    {
        Cache::forever(OrderListStats::VERSION_KEY, 5);
        $stats = $this->app->make(OrderListStats::class);
        $before = $stats->cacheKey('{}');

        $stats->invalidate();

        $this->assertSame(6, (int) Cache::get(OrderListStats::VERSION_KEY));
        $this->assertNotSame($before, $stats->cacheKey('{}'));
    }

    public function test_invalidate_waits_until_the_transaction_commits_and_bumps_once(): void
    {
        Cache::forever(OrderListStats::VERSION_KEY, 5);
        $stats = $this->app->make(OrderListStats::class);

        DB::beginTransaction();
        $stats->invalidate();
        $stats->invalidate();
        $this->assertSame(5, (int) Cache::get(OrderListStats::VERSION_KEY));
        DB::commit();

        $this->assertSame(6, (int) Cache::get(OrderListStats::VERSION_KEY));
    }

    public function test_only_pricing_fields_on_an_order_throw_the_cache_away(): void
    {
        $order = new Order;
        $order->syncOriginal();
        $order->comment = 'note for the team';
        $order->syncChanges();

        $this->assertFalse(OrderListStats::orderAffectsTotals($order));

        $order->status = 'finished';
        $order->syncChanges();

        $this->assertTrue(OrderListStats::orderAffectsTotals($order));

        $created = new Order;
        $created->wasRecentlyCreated = true;

        $this->assertTrue(OrderListStats::orderAffectsTotals($created));
    }

    public function test_a_payment_without_an_order_does_not_change_the_totals(): void
    {
        $payment = new Payment;
        $payment->syncOriginal();

        $this->assertFalse(OrderListStats::paymentAffectsTotals($payment));

        $payment->order_id = 15;
        $payment->syncChanges();

        $this->assertTrue(OrderListStats::paymentAffectsTotals($payment));
    }

    /**
     * @return array{ordersCount: int, totalPriceGel: float, totalExpenses: float, totalPaid: float, totalUnpaid: float}
     */
    private function sampleTotals(): array
    {
        return [
            'ordersCount' => 2,
            'totalPriceGel' => 100.5,
            'totalExpenses' => 3.25,
            'totalPaid' => 40.0,
            'totalUnpaid' => 60.5,
        ];
    }
}
