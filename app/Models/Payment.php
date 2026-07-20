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

    const TYPE_ORDER = 'Order';
    const TYPE_DEBT = 'Debt';

    protected $fillable = [
        'client_id',
        'order_id',
        'amount_gel',
        'currency_rate',
        'method',
        'type',
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
     * Normalise an empty order selection to null.
     *
     * The Order select on the payment form submits an empty string when no order
     * is chosen (or when the payment isn't of type "Order"); coerce it to null so
     * it stores cleanly in the nullable integer column.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setOrderIdAttribute($value)
    {
        $this->attributes['order_id'] = ($value === '' || $value === null) ? null : $value;
    }

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
     * Payment types, keyed by the value stored in the database.
     *
     * @return array
     */
    public static function types()
    {
        return [
            self::TYPE_ORDER => 'შეკვეთა',
            self::TYPE_DEBT => 'ვალი',
        ];
    }

    /**
     * Get the client that owns the payment.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the order this payment is directly related to (optional).
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
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

