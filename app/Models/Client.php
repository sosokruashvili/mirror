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
        'phone_number'
    ];
    
    protected $casts = [
        'client_type' => 'boolean'
    ];

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
     * Calculate the client's balance.
     * Balance = sum of paid payments - sum of orders total price (excluding draft orders)
     * 
     * @return float
     */
    public function calculateBalance()
    {
        // Only count payments with status 'Paid'
        $paymentsSum = $this->payments()->where('status', 'Paid')->sum('amount_gel') ?? 0;
        
        // Only count orders that are not in 'draft' status
        $ordersSum = $this->orders()->where('status', '!=', 'draft')->get()->sum(function($order) {
            return $order->calculateTotalPrice();
        });
        
        return $paymentsSum - $ordersSum;
    }
}
