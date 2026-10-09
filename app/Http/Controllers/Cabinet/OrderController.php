<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\ClientAccount;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = $this->orders($request)
            ->with(['services', 'products', 'pieces', 'payments'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('cabinet.orders.index', [
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, int $order)
    {
        $order = $this->orders($request)
            ->with([
                'services',
                'products',
                'pieces.product',
                'pieces.stages',
                'payments',
            ])
            ->whereKey($order)
            ->firstOrFail();

        $total = (float) $order->calculateTotalPrice(false);
        $paid = (float) $order->payments->where('status', 'Paid')->sum('amount_gel');

        return view('cabinet.orders.show', [
            'order' => $order,
            'total' => $total,
            'paid' => $paid,
        ]);
    }

    protected function orders(Request $request): Builder
    {
        return Order::query()
            ->where('client_id', $this->account($request)->client_id)
            ->where('status', '!=', 'draft');
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
