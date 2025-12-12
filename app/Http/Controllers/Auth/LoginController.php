<?php

namespace App\Http\Controllers\Auth;

use Backpack\CRUD\app\Http\Controllers\Auth\LoginController as BackpackLoginController;

class LoginController extends BackpackLoginController
{
    /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated($request, $user)
    {
        // Redirect team users to their order processing page
        if ($user->hasRole('team')) {
            return redirect()->route('team.orders');
        }

        // Default redirect for other users
        return redirect()->intended($this->redirectPath());
    }

    /**
     * Get the post-login redirect path.
     *
     * @return string
     */
    protected function redirectTo()
    {
        $user = backpack_auth()->user();
        
        // Redirect team users to their order processing page
        if ($user && $user->hasRole('team')) {
            return route('team.orders');
        }

        // Default redirect to dashboard
        return backpack_url('dashboard');
    }
}

