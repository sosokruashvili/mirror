<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use CrudTrait;

    protected $fillable = [
        'product_id',
        'quantity',
        'area',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'area' => 'decimal:3',
    ];

    /**
     * The product that owns the warehouse entry.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
