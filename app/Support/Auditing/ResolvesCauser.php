<?php

namespace App\Support\Auditing;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the admin user behind the current request, for loggers that
 * denormalize a user_id + user_name onto their rows.
 *
 * Backpack authenticates on its own guard, so backpack_user() has to be tried
 * before the default auth()->user().
 */
trait ResolvesCauser
{
    protected function resolveCauser(): ?Model
    {
        if (function_exists('backpack_user') && ($user = backpack_user())) {
            return $user;
        }

        return auth()->user();
    }

    protected function causerName(?Model $causer): ?string
    {
        if (! $causer) {
            return null;
        }

        return $causer->name
            ?? $causer->email
            ?? (class_basename($causer) . ' #' . $causer->getKey());
    }
}
