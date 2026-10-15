<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\ClientAccount;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = Notification::query()
            ->visibleInCabinet($this->account($request)->client)
            ->orderByDesc('id')
            ->paginate(20);

        return view('cabinet.notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    protected function account(Request $request): ClientAccount
    {
        $account = $request->attributes->get('cabinetAccount');

        if (! $account instanceof ClientAccount || ! $account->client) {
            abort(403);
        }

        return $account;
    }
}
