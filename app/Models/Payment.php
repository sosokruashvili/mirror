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
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        // Update order payment status after payment is created or updated
        static::saved(function ($payment) {
            static::updateOrderPaymentStatus();
        });

        // Update order payment status after payment is deleted
        static::deleted(function ($payment) {
            static::updateOrderPaymentStatus();
        });
    }

    /**
     * Get the client that owns the payment.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Update order payment status based on client balances.
     * 
     * @return void
     */
    public static function updateOrderPaymentStatus() {
        $orders = Order::where('paid', false)->where('status', '!=', 'draft')->get();
        foreach($orders as $order) {
            $client = $order->client;
            if($client->balance >= $order->calculateTotalPrice()) {
                $order->paid = true;
                $order->save();
            }
        }
    }

}

