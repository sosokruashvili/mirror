<?php

namespace App\Models;

use App\Services\OrderListStats;
use App\Services\PaymentAllocation;
use App\Support\Auditing\AuditLogger;
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

        // The order_payment rows go with the payment (foreign key cascade), so
        // read them first: the deleted hooks need to know which orders lost it.
        static::deleting(function (Payment $payment) {
            $payment->loadMissing('orders');
        });

        static::deleted(function (Payment $payment) {
            $clientIds = $payment->orders->pluck('client_id')->push($payment->client_id);

            foreach ($clientIds->filter()->unique() as $clientId) {
                static::updateOrderPaymentStatus($clientId);
            }
        });
    }

    /**
     * Payment types, keyed by the value stored in the database.
     *
     * Keys stay in English (they are the stored values and are used in
     * validation rules); only the labels follow the active locale.
     *
     * @return array<string, string>
     */
    public static function types()
    {
        return [
            self::TYPE_ORDER => __('payment.types.'.self::TYPE_ORDER),
            self::TYPE_DEBT => __('payment.types.'.self::TYPE_DEBT),
        ];
    }

    /**
     * Payment methods in display order, keyed by the value stored in the database.
     *
     * As with types(), the keys are the stored values; the labels are localized.
     *
     * @return array<string, string>
     */
    public static function methods(): array
    {
        return [
            self::METHOD_CASH => __('payment.methods.'.self::METHOD_CASH),
            self::METHOD_TRANSFER => __('payment.methods.'.self::METHOD_TRANSFER),
            self::METHOD_TERMINAL => __('payment.methods.'.self::METHOD_TERMINAL),
            self::METHOD_PM_TRANSFER => __('payment.methods.'.self::METHOD_PM_TRANSFER),
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
     * The orders this payment covers (none, one, or several).
     *
     * pivot.amount is each order's share of the payment, worked out by
     * App\Services\PaymentAllocation; it is never entered by hand.
     */
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_payment')->withPivot('amount');
    }

    /**
     * Point the payment at exactly these orders, then redo the split and the
     * paid flags. Orders that were unlinked may belong to another client (an
     * old link, or the payment moved client), so their client is synced too.
     *
     * @param  array<int|string|null>  $orderIds
     */
    public function linkOrders(array $orderIds): void
    {
        $orderIds = collect($orderIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $before = $this->orders()->pluck('orders.id')->map(fn ($id) => (int) $id)->all();
        $changes = $this->orders()->sync($orderIds);
        $this->unsetRelation('orders');

        $clientIds = collect([$this->client_id]);

        if (!empty($changes['detached'])) {
            $clientIds = $clientIds->merge(Order::whereKey($changes['detached'])->pluck('client_id'));
        }

        if (!empty($changes['attached']) || !empty($changes['detached'])) {
            // order_payment rows fire no model events, so neither of these would notice.
            app(AuditLogger::class)->linksChanged($this, 'order_ids', $before, $orderIds);
            app(OrderListStats::class)->invalidate();
        }

        foreach ($clientIds->filter()->unique() as $clientId) {
            static::updateOrderPaymentStatus($clientId);
        }
    }

    /**
     * Sync orders.paid, and each order's share of its payments, for a client.
     *
     * Kept under this name because callers outside the payment form use it;
     * the work is done by PaymentAllocation.
     *
     * @param  int|null  $clientId
     * @return void
     */
    public static function updateOrderPaymentStatus($clientId)
    {
        app(PaymentAllocation::class)->sync($clientId ? (int) $clientId : null);
    }
}
