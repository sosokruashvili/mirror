<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $table = 'clients';
    protected $guarded = ['id'];
    
    protected $fillable = [
        'name',
        'email',
        'client_type',
        'personal_id',
        'legal_id',
        'address',
        'phone_number',
        'starting_balance'
    ];

    protected $casts = [
        'client_type' => 'boolean',
        'starting_balance' => 'decimal:2'
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($client) {
            foreach (['personal_id', 'legal_id', 'email'] as $field) {
                if ($client->{$field} === '') {
                    $client->{$field} = null;
                }
            }
        });
    }

    /**
     * Get the display name with ID for select options.
     * Shows name followed by legal_id or personal_id (whichever is not empty).
     */
    public function getNameWithIdAttribute()
    {
        $id = $this->legal_id ?: $this->personal_id;
        if ($id) {
            return $this->name . ' (' . $id . ')';
        }
        return $this->name;
    }

    /**
     * The orders that belong to the client.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The payments that belong to the client.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The daily balance snapshots for the client.
     */
    public function balances()
    {
        return $this->hasMany(ClientBalance::class);
    }

    /**
     * The most recent daily balance snapshot for the client.
     */
    public function latestBalance()
    {
        return $this->hasOne(ClientBalance::class)->latestOfMany('balance_date');
    }

    /**
     * Calculate the client's balance.
     * Balance = starting balance + sum of paid payments - sum of orders total price (excluding draft orders)
     *
     * @return float
     */
    public function calculateBalance()
    {
        // Manually entered opening balance carried over from before the system.
        $startingBalance = (float) ($this->starting_balance ?? 0);

        // Only count payments with status 'Paid'
        $paymentsSum = $this->payments()->where('status', 'Paid')->sum('amount_gel') ?? 0;

        // Only count orders that are not in 'draft' status
        $ordersSum = $this->orders()->where('status', '!=', 'draft')->get()->sum(function($order) {
            return $order->calculateTotalPrice();
        });

        return $startingBalance + $paymentsSum - $ordersSum;
    }

    public function getBalanceAttribute()
    {
        return $this->calculateBalance();
    }
}
