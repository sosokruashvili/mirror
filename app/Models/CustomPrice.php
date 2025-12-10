<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class CustomPrice extends Model
{
    use CrudTrait;

    protected $fillable = [
        'client_id',
        'product_id',
        'price_usd',
    ];

    protected $casts = [
        'price_usd' => 'decimal:2',
    ];

    /**
     * The client that owns this custom price.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The product for this custom price.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
