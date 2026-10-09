<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\ClientAccount;
use App\Support\Cabinet\Cabinet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * A valid bcrypt hash used only so a missing account takes about as long
     * to reject as a wrong password. It is never a real account's password.
     */
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function create()
    {
        return view('cabinet.login');
    }

    public function store(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $digits = Cabinet::phoneDigits($request->input('phone'));
        $account = $digits === ''
            ? null
            : ClientAccount::query()->where('phone_digits', $digits)->first();

        $hash = $account?->getRawOriginal('password') ?: self::DUMMY_HASH;

        $passwordOk = Hash::check($request->input('password'), $hash);

        if (! $account || ! $account->is_active || ! $passwordOk) {
            throw ValidationException::withMessages([
                'login' => __('cabinet.login_failed'),
            ]);
        }

        Auth::guard('cabinet')->login($account);
        $request->session()->regenerate();
        $request->session()->put('cabinet_session_version', (int) $account->session_version);

        $account->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('cabinet.home'));
    }

    public function destroy(Request $request)
    {
        Auth::guard('cabinet')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('cabinet.login');
    }
}
