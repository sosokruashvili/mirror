<?php

namespace Tests\Unit;

use App\Services\PaymentAllocation;
use PHPUnit\Framework\TestCase;

/**
 * The split of payments over orders (PaymentAllocation::split), on plain arrays.
 */
class PaymentAllocationTest extends TestCase
{
    public function test_a_single_order_payment_goes_to_its_order_in_full(): void
    {
        $shares = PaymentAllocation::split([1 => 100.0], [
            10 => $this->payment(150, [1]),
        ]);

        $this->assertSame([10 => [1 => 150.0]], $shares);
    }

    public function test_a_payment_that_covers_its_orders_pays_each_one_off(): void
    {
        $shares = PaymentAllocation::split([1 => 200.0, 2 => 300.0], [
            10 => $this->payment(500, [1, 2]),
        ]);

        $this->assertSame([10 => [1 => 200.0, 2 => 300.0]], $shares);
    }

    public function test_a_short_payment_leaves_every_order_short(): void
    {
        $shares = PaymentAllocation::split([1 => 200.0, 2 => 300.0], [
            10 => $this->payment(400, [1, 2]),
        ]);

        $this->assertSame([10 => [1 => 160.0, 2 => 240.0]], $shares);
        $this->assertLessThan(200.0 - PaymentAllocation::TOLERANCE, $shares[10][1]);
        $this->assertLessThan(300.0 - PaymentAllocation::TOLERANCE, $shares[10][2]);
    }

    public function test_money_beyond_the_debts_is_spread_by_order_total(): void
    {
        $shares = PaymentAllocation::split([1 => 200.0, 2 => 300.0], [
            10 => $this->payment(600, [1, 2]),
        ]);

        $this->assertSame([10 => [1 => 240.0, 2 => 360.0]], $shares);
    }

    public function test_single_order_payments_are_counted_before_a_shared_one(): void
    {
        // Order 1 already has 100 of its 300 paid; the shared payment covers the rest
        // of order 1 (200) and all of order 2 (200).
        $shares = PaymentAllocation::split([1 => 300.0, 2 => 200.0], [
            11 => $this->payment(400, [1, 2], '2026-11-02 10:00:00'),
            10 => $this->payment(100, [1], '2026-11-05 10:00:00'),
        ]);

        $this->assertSame(100.0, $shares[10][1]);
        $this->assertSame([1 => 200.0, 2 => 200.0], $shares[11]);
    }

    public function test_older_shared_payments_are_applied_first(): void
    {
        $shares = PaymentAllocation::split([1 => 100.0, 2 => 100.0, 3 => 100.0], [
            21 => $this->payment(200, [2, 3], '2026-11-03 09:00:00'),
            20 => $this->payment(200, [1, 2], '2026-11-01 09:00:00'),
        ]);

        // Payment 20 pays off orders 1 and 2, so 21 only finds order 3 owing.
        $this->assertSame([1 => 100.0, 2 => 100.0], $shares[20]);
        $this->assertSame(200.0, round($shares[21][2] + $shares[21][3], 2));
        $this->assertGreaterThanOrEqual(100.0, $shares[21][3]);
    }

    public function test_shares_add_up_to_the_payment_to_the_tetri(): void
    {
        $shares = PaymentAllocation::split([1 => 10.0, 2 => 10.0, 3 => 10.0], [
            10 => $this->payment(20, [1, 2, 3]),
        ]);

        $this->assertSame(20.0, round(array_sum($shares[10]), 2));
        foreach ($shares[10] as $share) {
            $this->assertEqualsWithDelta(6.67, $share, 0.011);
        }
    }

    public function test_orders_with_no_total_split_a_payment_evenly(): void
    {
        $shares = PaymentAllocation::split([1 => 0.0, 2 => 0.0], [
            10 => $this->payment(50, [1, 2]),
        ]);

        $this->assertSame([10 => [1 => 25.0, 2 => 25.0]], $shares);
    }

    /**
     * @param  list<int>  $orders
     * @return array{amount: float, date: string, orders: list<int>}
     */
    private function payment(float $amount, array $orders, string $date = '2026-11-01 12:00:00'): array
    {
        return ['amount' => $amount, 'date' => $date, 'orders' => $orders];
    }
}
