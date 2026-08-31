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

        // Use the already-loaded relations when the caller eager loaded them
        // (flushDirtyClients does), and fall back to a query when it didn't.
        $payments = $client->relationLoaded('payments')
            ? $client->payments
            : $client->payments()->get();

        $orders = $client->relationLoaded('orders')
            ? $client->orders
            : $client->orders()->get();

        $paymentsTotal = (float) $payments->where('status', 'Paid')->sum('amount_gel');

        $ordersTotal = (float) $orders
            ->where('status', '!=', 'draft')
            ->sum(function ($order) {
                // Read-only: never write piece prices back while reading a balance.
                return $order->calculateTotalPrice(false);
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

        Client::query()
            ->with(['payments', 'orders.services', 'orders.products', 'orders.pieces'])
            ->chunkById(200, function ($clients) use ($date, &$count) {
                foreach ($clients as $client) {
                    ClientBalance::updateOrCreate(
                        [
                            'client_id' => $client->id,
                            'balance_date' => $date->toDateString(),
                        ],
                        $this->calculateComponentsForClient($client)
                    );

                    $count++;
                }
            });

        return $count;
    }

    /**
     * Rewrite today's snapshot for a single client from their current data.
     *
     * Past snapshots are left alone: they are the historical record behind the
     * "balance as of" filter. Only today's row is re-derived.
     */
    public function refreshForClient(Client $client, ?Carbon $date = null): ClientBalance
    {
        $date = ($date ?? now())->copy()->startOfDay();

        return ClientBalance::updateOrCreate(
            [
                'client_id' => $client->id,
                'balance_date' => $date->toDateString(),
            ],
            $this->calculateComponentsForClient($client)
        );
    }

    /**
     * Flag a client whose orders, pieces or payments just changed, so today's
     * snapshot is re-derived before the process ends.
     *
     * Deferred rather than immediate on purpose: saving one order writes every
     * one of its pieces, and recomputing per piece would both waste work and
     * snapshot half-saved intermediate states. Collecting the ids and flushing
     * once, after the response, recomputes each client exactly once from the
     * finished data.
     */
    public function markClientDirty(?int $clientId): void
    {
        if (!$clientId) {
            return;
        }

        $this->dirtyClientIds[$clientId] = true;

        if (!$this->flushScheduled) {
            $this->flushScheduled = true;
            app()->terminating(fn () => $this->flushDirtyClients());
        }
    }

    /**
     * Re-derive today's snapshot for every client flagged by markClientDirty().
     *
     * @return int Number of clients refreshed.
     */
    public function flushDirtyClients(): int
    {
        $ids = array_keys($this->dirtyClientIds);

        $this->dirtyClientIds = [];
        $this->flushScheduled = false;

        if (!$ids) {
            return 0;
        }

        $count = 0;

        // calculateComponentsForClient() prices every order, which walks its
        // services, products and pieces - eager load them so a multi-client
        // flush stays a handful of queries.
        Client::query()
            ->whereIn('id', $ids)
            ->with(['payments', 'orders.services', 'orders.products', 'orders.pieces'])
            ->get()
            ->each(function (Client $client) use (&$count) {
                $this->refreshForClient($client);
                $count++;
            });

        return $count;
    }

    /**
     * Client ids flagged by markClientDirty(), pending a flush.
     *
     * @var array<int, true>
     */
    protected array $dirtyClientIds = [];

    /** Whether a terminating callback is already queued to flush them. */
    protected bool $flushScheduled = false;

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
