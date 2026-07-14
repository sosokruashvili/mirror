<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientBalance;
use Carbon\Carbon;

class ClientBalanceService
{
    /**
     * Compute the current balance components for a single client.
     *
     * balance = starting balance + sum of Paid payments - sum of non-draft orders'
     * total price (uses the live calculateTotalPrice(), matching Client::calculateBalance()).
     *
     * @return array{starting_balance: float, payments_total: float, orders_total: float, balance: float}
     */
    public function calculateComponentsForClient(Client $client): array
    {
        $startingBalance = (float) ($client->starting_balance ?? 0);

        $paymentsTotal = (float) $client->payments()
            ->where('status', 'Paid')
            ->sum('amount_gel');

        $ordersTotal = (float) $client->orders()
            ->where('status', '!=', 'draft')
            ->get()
            ->sum(function ($order) {
                return $order->calculateTotalPrice();
            });

        return [
            'starting_balance' => $startingBalance,
            'payments_total' => $paymentsTotal,
            'orders_total' => $ordersTotal,
            'balance' => $startingBalance + $paymentsTotal - $ordersTotal,
        ];
    }

    /**
     * Snapshot the current balance for every client on the given date.
     * Re-running for the same date overwrites that date's snapshot.
     *
     * @return int Number of clients snapshotted.
     */
    public function snapshotDailyBalances(?Carbon $date = null): int
    {
        $date = ($date ?? now())->copy()->startOfDay();
        $count = 0;

        Client::query()->chunkById(200, function ($clients) use ($date, &$count) {
            foreach ($clients as $client) {
                $components = $this->calculateComponentsForClient($client);

                ClientBalance::updateOrCreate(
                    [
                        'client_id' => $client->id,
                        'balance_date' => $date->toDateString(),
                    ],
                    $components
                );

                $count++;
            }
        });

        return $count;
    }

    /**
     * Get the latest stored balance snapshot for a client, falling back to a
     * live calculation when no snapshot exists yet (e.g. before the first run).
     */
    public function getStoredBalance(Client $client): float
    {
        $snapshot = $client->latestBalance;

        if ($snapshot) {
            return (float) $snapshot->balance;
        }

        return (float) $client->calculateBalance();
    }
}
