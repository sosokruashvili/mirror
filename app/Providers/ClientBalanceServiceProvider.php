<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Piece;
use App\Services\ClientBalanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

/**
 * Keeps the stored client balance snapshots current.
 *
 * The client balances screen reads today's row from client_balances rather than
 * pricing every order on each page load. Without this provider that row was only
 * written by the nightly clients:snapshot-balances run and the manual
 * "Recalculate" button, so an order edited during the day showed its new price in
 * the expanded order list while the balance columns above kept yesterday's number.
 *
 * Every model that feeds a balance therefore flags its client on save and delete,
 * and App\Services\ClientBalanceService re-derives today's snapshot once, after
 * the response.
 *
 * Note: changing a product's catalog price re-prices orders across many clients
 * at once and is not covered here; those balances settle on the next nightly run
 * (or a Recalculate click).
 */
class ClientBalanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton so the pending-client set survives the whole request.
        $this->app->singleton(ClientBalanceService::class);
    }

    public function boot(): void
    {
        // An order carries the client, its status decides whether it counts at
        // all, and moving it to another client changes two balances.
        $this->onWrite(Order::class, function (Order $order) {
            return [$order->client_id, $order->getOriginal('client_id')];
        });

        // Pieces are priced individually, so adding, resizing or removing one
        // changes its order's total.
        $this->onWrite(Piece::class, function (Piece $piece) {
            return [Order::whereKey($piece->order_id)->value('client_id')];
        });

        // Only 'Paid' payments count, so a status change matters as much as an
        // amount change; both arrive here as a plain save.
        $this->onWrite(Payment::class, function (Payment $payment) {
            return [$payment->client_id, $payment->getOriginal('client_id')];
        });

        // The manually entered opening balance is part of the total.
        $this->onWrite(Client::class, function (Client $client) {
            return [$client->id];
        });
    }

    /**
     * Flag the clients returned by $resolver whenever a model of $class is
     * saved or deleted.
     *
     * @param  class-string<Model>  $class
     * @param  callable(Model): array<int|null>  $resolver
     */
    protected function onWrite(string $class, callable $resolver): void
    {
        $mark = function (Model $model) use ($resolver) {
            $service = $this->app->make(ClientBalanceService::class);

            foreach ($resolver($model) as $clientId) {
                $service->markClientDirty($clientId ? (int) $clientId : null);
            }
        };

        $class::saved($mark);
        $class::deleted($mark);
    }
}
