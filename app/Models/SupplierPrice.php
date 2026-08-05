<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPrice extends Model
{
    use CrudTrait;

    protected $fillable = [
        'product_id',
        'supplier_id',
        'price_usd',
    ];

    protected $casts = [
        'price_usd' => 'decimal:2',
    ];

    /**
     * The product this purchase price applies to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The supplier offering this price.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
