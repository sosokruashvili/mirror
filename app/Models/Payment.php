<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $table = 'payments';
    protected $guarded = ['id'];
    
    protected $fillable = [
        'client_id',
        'order_id',
        'amount_gel',
        'currency_rate',
        'method',
        'status',
        'file',
        'payment_date'
    ];
    
    protected $casts = [
        'amount_gel' => 'decimal:2',
        'currency_rate' => 'decimal:4',
        'payment_date' => 'datetime'
    ];

    /**
     * Get the client that owns the payment.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the order that this payment belongs to.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

