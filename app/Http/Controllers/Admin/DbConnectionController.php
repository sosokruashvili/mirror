<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DevEnvironment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Prologue\Alerts\Facades\Alert;

/**
 * Developer-only switch between the production and development databases
 * for the current session (topbar selector). See SwitchDatabaseConnection.
 */
class DbConnectionController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless(isDeveloper(), 403);

        $data = $request->validate([
            'connection' => ['required', 'in:prod,dev'],
        ]);

        if (DevEnvironment::isActive()) {
            Alert::info(__('dbswitch.host_locked', ['host' => $request->getHost()]))->flash();

            return redirect()->back();
        }

        if ($data['connection'] === 'dev') {
            if (! config('database.connections.'.DevEnvironment::devConnection().'.database')) {
                Alert::error(__('dbswitch.not_configured'))->flash();

                return redirect()->back();
            }

            $request->session()->put(DevEnvironment::SESSION_KEY, 'dev');
            Alert::warning(__('dbswitch.switched_dev', ['db' => DevEnvironment::devDatabaseName()]))->flash();
        } else {
            $request->session()->forget(DevEnvironment::SESSION_KEY);
            Alert::success(__('dbswitch.switched_prod', ['db' => DevEnvironment::prodDatabaseName()]))->flash();
        }

        return redirect()->back();
    }
}
