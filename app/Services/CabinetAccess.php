<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientAccount;
use App\Support\Cabinet\Cabinet;
use Illuminate\Validation\ValidationException;

class CabinetAccess
{
    /**
     * Create, update, or disable the cabinet login from the client form.
     * Runs only when that form posted cabinet_enabled, so other client saves
     * leave the login alone.
     */
    public function sync(Client $client): void
    {
        if (! request()->has('cabinet_enabled')) {
            return;
        }

        $account = ClientAccount::query()->where('client_id', $client->id)->first();
        $enabled = request()->boolean('cabinet_enabled');
        $password = request()->input('cabinet_password');
        $password = is_string($password) ? $password : '';

        if (! $enabled) {
            if ($account && $account->is_active) {
                $account->is_active = false;
                $account->session_version++;
                $account->save();
            }

            return;
        }

        $digits = Cabinet::phoneDigits($client->phone_number);

        if (strlen($digits) < 9) {
            throw ValidationException::withMessages([
                'phone_number' => __('client.cabinet.phone_invalid'),
            ]);
        }

        $taken = ClientAccount::query()
            ->where('phone_digits', $digits)
            ->when($account, fn ($query) => $query->whereKeyNot($account->id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'phone_number' => __('client.cabinet.phone_taken'),
            ]);
        }

        if (! $account) {
            if ($password === '') {
                throw ValidationException::withMessages([
                    'cabinet_password' => __('client.cabinet.password_required'),
                ]);
            }

            ClientAccount::query()->create([
                'client_id' => $client->id,
                'phone_digits' => $digits,
                'password' => $password,
                'is_active' => true,
                'session_version' => 1,
            ]);

            return;
        }

        $account->phone_digits = $digits;
        $account->is_active = true;

        if ($password !== '') {
            $account->password = $password;
            $account->session_version++;
        }

        $account->save();
    }
}
