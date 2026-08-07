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

    const METHOD_CASH = 'Cash';
    const METHOD_TRANSFER = 'Transfer';
    const METHOD_TERMINAL = 'Terminal';
    const METHOD_PM_TRANSFER = 'PM Transfer';

    protected $fillable = [
        'author',
        'client_id',
        'order_id',
        'amount_gel',
        'currency_rate',
        'method',
        'type',
        'status',
        'file',
        'payment_date',
        'idempotency_key'
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
        // Match orders.author: stamp the creating admin on insert (CRUD + AJAX).
        static::creating(function (Payment $payment) {
            if ($payment->author === null && function_exists('backpack_user') && backpack_user()) {
                $payment->author = backpack_user()->id;
            }
        });

        static::saved(function (Payment $payment) {
            static::updateOrderPaymentStatus($payment->client_id);

            if ($payment->wasChanged('client_id') && $payment->getOriginal('client_id')) {
                static::updateOrderPaymentStatus($payment->getOriginal('client_id'));
            }
        });

        static::deleted(function (Payment $payment) {
            static::updateOrderPaymentStatus($payment->client_id);
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
     * Payment methods in display order, keyed by the value stored in the database.
     *
     * @return array<string, string>
     */
    public static function methods(): array
    {
        return [
            self::METHOD_CASH => 'Cash',
            self::METHOD_TRANSFER => 'Transfer',
            self::METHOD_TERMINAL => 'Terminal',
            self::METHOD_PM_TRANSFER => 'PM Transfer',
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
     * The user who created the payment (stored on payments.author).
     *
     * Named authorUser() rather than author() on purpose: a relationship method
     * that shares its name with the real `author` column collides, and Eloquent
     * returns the raw column value instead of loading the relation.
     */
    public function authorUser()
    {
        return $this->belongsTo(User::class, 'author');
    }

    /**
     * Get the order this payment is directly related to (optional).
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Sync orders.paid for the client's non-draft orders from linked payments.
     *
     * An order is paid when it has at least one Paid payment attached and the
     * shortfall vs the order total is at most 1 GEL (covers rounding). Larger
     * overpayments still count as paid.
     *
     * @param  int|null  $clientId
     * @return void
     */
    public static function updateOrderPaymentStatus($clientId)
    {
        if (!$clientId) {
            return;
        }

        $orders = Order::where('client_id', $clientId)
            ->where('status', '!=', 'draft')
            ->with(['payments', 'services', 'products', 'pieces'])
            ->get();

        foreach ($orders as $order) {
            $paidAmount = (float) $order->payments
                ->where('status', 'Paid')
                ->sum('amount_gel');

            $orderTotal = (float) $order->calculateTotalPrice(false);
            $isPaid = $paidAmount > 0 && ($orderTotal - $paidAmount) <= 1.0;

            if ((bool) $order->paid !== $isPaid) {
                $order->paid = $isPaid;
                $order->saveQuietly();
            }
        }
    }
}

