<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use CrudTrait;

    protected $fillable = [
        'product_id',
        'unit_of_measure',
        'value',
    ];

    protected $casts = [
        'value' => 'decimal:3',
    ];

    /**
     * The product that owns the warehouse entry.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
