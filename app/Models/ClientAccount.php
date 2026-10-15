<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Login for one client. Kept off the staff users table and off the clients
 * table so a cabinet password can never be loaded into Backpack as a column.
 */
class ClientAccount extends Authenticatable
{
    protected $fillable = [
        'client_id',
        'phone_digits',
        'password',
        'is_active',
        'session_version',
        'last_login_at',
        'access_token_hash',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'session_version' => 'integer',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
