<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Switch the admin-panel language and send the user back where they were.
     *
     * The choice is always stored in the session (so it works on the login
     * page, before there is a user) and additionally persisted on the user
     * row when someone is logged in, so it follows them to other devices.
     */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('locales.supported', [])), 404);

        $request->session()->put('locale', $locale);

        if ($user = backpack_auth()->user()) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return back();
    }
}
