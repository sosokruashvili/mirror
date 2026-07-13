<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class ClientBalance extends Model
{
    use CrudTrait;

    protected $fillable = [
        'client_id',
        'balance_date',
        'payments_total',
        'orders_total',
        'balance',
    ];

    protected $casts = [
        'balance_date' => 'date',
        'payments_total' => 'decimal:2',
        'orders_total' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    /**
     * The client this balance snapshot belongs to.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
