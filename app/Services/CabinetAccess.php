<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientAccount;
use App\Models\Notification;
use App\Models\SmsTemplate;
use App\Support\Cabinet\Cabinet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

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
            if ($account && ($account->is_active || $account->access_token_hash)) {
                $account->is_active = false;
                $account->access_token_hash = null;
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

    /**
     * Turn the cabinet on and mint a new access token. The previous link stops
     * working. The plain token is returned once so it can be put in the
     * notification; only its hash is stored.
     */
    public function issueToken(Client $client): string
    {
        $digits = Cabinet::phoneDigits($client->phone_number);

        if (strlen($digits) < 9) {
            throw new InvalidArgumentException(__('client.cabinet.phone_invalid'));
        }

        $account = ClientAccount::query()->where('client_id', $client->id)->first();

        $taken = ClientAccount::query()
            ->where('phone_digits', $digits)
            ->when($account, fn ($query) => $query->whereKeyNot($account->id))
            ->exists();

        if ($taken) {
            throw new InvalidArgumentException(__('client.cabinet.phone_taken'));
        }

        $plain = bin2hex(random_bytes(4));

        if (! $account) {
            ClientAccount::query()->create([
                'client_id' => $client->id,
                'phone_digits' => $digits,
                'password' => Str::password(40),
                'is_active' => true,
                'session_version' => 1,
                'access_token_hash' => Cabinet::hashAccessToken($plain),
            ]);

            return $plain;
        }

        $account->phone_digits = $digits;
        $account->is_active = true;
        $account->access_token_hash = Cabinet::hashAccessToken($plain);
        $account->save();

        return $plain;
    }

    /**
     * Allow the cabinet and queue the SMS that carries this client's private link.
     */
    public function allow(Client $client): Notification
    {
        return DB::transaction(function () use ($client) {
            $token = $this->issueToken($client);

            return $client->queueSms(SmsTemplate::findByKey(SmsTemplate::KEY_CABINET_ACCESS), null, [
                'cabinet_url' => Cabinet::accessUrl($token),
            ]);
        });
    }
}
