<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Splits payments over the orders they are linked to, and keeps orders.paid and
 * the stored shares (order_payment.amount) in step with that split.
 *
 * Users only pick which orders a payment covers; they never say how much of it
 * goes where. The split is worked out here:
 *
 *   1. A payment linked to one order goes to that order in full, overpayment
 *      included, as it always has.
 *   2. Payments linked to several orders come after those, oldest first. Each
 *      order gets what it still owes. When the payment cannot cover all of it,
 *      every order gets the same fraction of its debt, so none of them reaches
 *      "paid". Money left once every debt is covered is spread by order total.
 *
 * A multi-order payment that does not cover its orders cannot be saved (see
 * PaymentRequest), so the short case only shows up when an order's total goes
 * up after the payment.
 */
class PaymentAllocation
{
    /** A shortfall of up to this many GEL still counts as paid (rounding). */
    public const TOLERANCE = 1.0;

    public function __construct(private OrderListStats $listStats)
    {
    }

    /**
     * Recompute the stored shares and the paid flag of every order of a client.
     */
    public function sync(?int $clientId): void
    {
        if (!$clientId) {
            return;
        }

        $orders = $this->ordersOf($clientId);
        $totals = $this->totals($orders);
        $shares = static::split($totals, $this->paymentsOf($orders));

        $changed = false;

        foreach ($orders as $order) {
            $paidShare = 0.0;

            foreach ($order->payments as $payment) {
                $share = $shares[$payment->id][$order->id] ?? 0.0;

                if (abs((float) $payment->pivot->amount - $share) >= 0.005) {
                    DB::table('order_payment')
                        ->where('payment_id', $payment->id)
                        ->where('order_id', $order->id)
                        ->update(['amount' => $share]);
                    $changed = true;
                }

                if ($payment->status === 'Paid') {
                    $paidShare += $share;
                }
            }

            // Drafts are not billed yet, so their flag is left alone (as before).
            if ($order->status === 'draft') {
                continue;
            }

            $isPaid = $paidShare > 0 && ($totals[$order->id] - $paidShare) <= self::TOLERANCE;

            if ((bool) $order->paid !== $isPaid) {
                $order->paid = $isPaid;
                $order->saveQuietly();
                $changed = true;
            }
        }

        if ($changed) {
            $this->listStats->invalidate();
        }
    }

    /**
     * What each of the client's orders still owes. A payment being edited is
     * left out, so its own share does not count against the orders it covers.
     *
     * @param  Collection<int, Order>|null  $orders  the client's orders, if the caller already
     *                                            loaded them with payments, services, products, pieces
     * @return array<int, float> order id => amount still owed, never negative
     */
    public function outstanding(int $clientId, ?int $exceptPaymentId = null, ?Collection $orders = null): array
    {
        $orders ??= $this->ordersOf($clientId);
        $totals = $this->totals($orders);

        $payments = $this->paymentsOf($orders);
        if ($exceptPaymentId !== null) {
            unset($payments[$exceptPaymentId]);
        }

        $applied = [];
        foreach (static::split($totals, $payments) as $byOrder) {
            foreach ($byOrder as $orderId => $share) {
                $applied[$orderId] = ($applied[$orderId] ?? 0.0) + $share;
            }
        }

        $owed = [];
        foreach ($totals as $orderId => $total) {
            $owed[$orderId] = max(0.0, round($total - ($applied[$orderId] ?? 0.0), 2));
        }

        return $owed;
    }

    /**
     * How much a payment covering several orders has to be, or null when it is
     * enough (or covers fewer than two orders, where part payment is fine).
     *
     * amount_gel is stored as whole lari, so the stored (rounded) amount is the
     * one that is checked.
     *
     * @param  array<int|string>  $orderIds
     */
    public function shortfall(int $clientId, array $orderIds, float $amount, ?int $exceptPaymentId = null): ?float
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));

        if (count($orderIds) < 2) {
            return null;
        }

        $owed = $this->outstanding($clientId, $exceptPaymentId);
        $needed = 0.0;
        foreach ($orderIds as $orderId) {
            $needed += $owed[$orderId] ?? 0.0;
        }
        $needed = round($needed, 2);

        return round($amount) + self::TOLERANCE >= $needed ? null : $needed;
    }

    /**
     * The split itself, on plain arrays so it can be checked without a database.
     *
     * @param  array<int, float>  $totals  order id => order total
     * @param  array<int, array{amount: float, date: string, orders: list<int>}>  $payments  payment id => payment
     * @return array<int, array<int, float>> payment id => [order id => share]
     */
    public static function split(array $totals, array $payments): array
    {
        $shares = [];
        $applied = [];

        foreach ($payments as $paymentId => $payment) {
            if (count($payment['orders']) === 1) {
                $orderId = $payment['orders'][0];
                $shares[$paymentId][$orderId] = round($payment['amount'], 2);
                $applied[$orderId] = ($applied[$orderId] ?? 0.0) + $payment['amount'];
            }
        }

        $multi = array_filter($payments, fn ($payment) => count($payment['orders']) > 1);
        uksort($multi, fn ($a, $b) => [$multi[$a]['date'], $a] <=> [$multi[$b]['date'], $b]);

        foreach ($multi as $paymentId => $payment) {
            $orderIds = array_values(array_unique($payment['orders']));
            sort($orderIds);

            $owed = [];
            foreach ($orderIds as $orderId) {
                $owed[$orderId] = max(0.0, ($totals[$orderId] ?? 0.0) - ($applied[$orderId] ?? 0.0));
            }

            $amount = $payment['amount'];
            $owedSum = array_sum($owed);

            if ($amount <= $owedSum) {
                $split = static::spread($amount, $owed);
            } else {
                $weights = [];
                foreach ($orderIds as $orderId) {
                    $weights[$orderId] = $totals[$orderId] ?? 0.0;
                }

                $split = [];
                foreach (static::spread($amount - $owedSum, $weights) as $orderId => $extra) {
                    $split[$orderId] = $owed[$orderId] + $extra;
                }
            }

            foreach (static::roundKeepingSum($split, $amount) as $orderId => $share) {
                $shares[$paymentId][$orderId] = $share;
                $applied[$orderId] = ($applied[$orderId] ?? 0.0) + $share;
            }
        }

        return $shares;
    }

    /**
     * Divide $amount in proportion to $weights (evenly when they are all zero).
     *
     * @param  array<int, float>  $weights
     * @return array<int, float>
     */
    private static function spread(float $amount, array $weights): array
    {
        $sum = array_sum($weights);
        $out = [];

        foreach ($weights as $key => $weight) {
            $out[$key] = $sum > 0 ? $amount * $weight / $sum : $amount / count($weights);
        }

        return $out;
    }

    /**
     * Round each part to tetri, putting the rounding difference on the last one
     * so the parts still add up to exactly the payment amount.
     *
     * @param  array<int, float>  $parts
     * @return array<int, float>
     */
    private static function roundKeepingSum(array $parts, float $amount): array
    {
        $last = array_key_last($parts);
        $sum = 0.0;

        foreach ($parts as $key => $part) {
            if ($key === $last) {
                $parts[$key] = round($amount - $sum, 2);
            } else {
                $parts[$key] = round($part, 2);
                $sum += $parts[$key];
            }
        }

        return $parts;
    }

    /**
     * @return Collection<int, Order>
     */
    private function ordersOf(int $clientId): Collection
    {
        return Order::where('client_id', $clientId)
            ->with(['payments', 'services', 'products', 'pieces'])
            ->get();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<int, float>
     */
    private function totals(Collection $orders): array
    {
        return $orders->mapWithKeys(function (Order $order) {
            return [$order->id => round((float) $order->calculateTotalPrice(false), 2)];
        })->all();
    }

    /**
     * The client's payments in the shape split() takes, each with the orders it
     * is linked to.
     *
     * @param  Collection<int, Order>  $orders
     * @return array<int, array{amount: float, date: string, orders: list<int>}>
     */
    private function paymentsOf(Collection $orders): array
    {
        $payments = [];

        foreach ($orders as $order) {
            foreach ($order->payments as $payment) {
                $payments[$payment->id] ??= [
                    'amount' => (float) $payment->amount_gel,
                    'date' => (string) optional($payment->payment_date)->format('Y-m-d H:i:s'),
                    'orders' => [],
                ];
                $payments[$payment->id]['orders'][] = $order->id;
            }
        }

        return $payments;
    }
}
