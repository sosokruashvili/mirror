<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\ClientAccount;
use App\Models\Order;
use App\Models\Payment;
use App\Services\ClientBalanceService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, ClientBalanceService $balances)
    {
        $account = $this->account($request);
        $client = $account->client()->with([
            'payments.order:id,client_id,status',
            'orders.services',
            'orders.products',
            'orders.pieces',
        ])->firstOrFail();

        $components = $balances->calculateComponentsForClient($client);

        $visibleOrders = $client->orders
            ->where('status', '!=', 'draft')
            ->sortByDesc('id')
            ->values();

        $recentPayments = $client->payments
            ->sortByDesc(fn (Payment $payment) => sprintf(
                '%s-%010d',
                $payment->payment_date?->format('Y-m-d H:i:s') ?? '',
                $payment->id
            ))
            ->take(5)
            ->values();

        return view('cabinet.home', [
            'client' => $client,
            'components' => $components,
            'openCount' => $visibleOrders->whereIn('status', ['new', 'working', 'ready'])->count(),
            'recentOrders' => $visibleOrders->take(5),
            'recentPayments' => $recentPayments,
        ]);
    }

    protected function account(Request $request): ClientAccount
    {
        $account = $request->attributes->get('cabinetAccount');

        if (! $account instanceof ClientAccount) {
            abort(403);
        }

        return $account;
    }
}
