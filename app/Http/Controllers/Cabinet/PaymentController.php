<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\ClientAccount;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $account = $request->attributes->get('cabinetAccount');

        if (! $account instanceof ClientAccount) {
            abort(403);
        }

        $payments = Payment::query()
            ->where('client_id', $account->client_id)
            ->with(['order:id,client_id,status'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('cabinet.payments.index', [
            'payments' => $payments,
        ]);
    }
}
