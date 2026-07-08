<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class CashierBalance extends Model
{
    use CrudTrait;

    protected $fillable = [
        'balance_date',
        'amount',
    ];

    protected $casts = [
        'balance_date' => 'date',
        'amount' => 'decimal:2',
    ];
}
