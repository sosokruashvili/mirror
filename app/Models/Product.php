<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use CrudTrait;

    protected $fillable = [
        'title',
        'product_type',
        'price',
        'price_w',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_w' => 'decimal:2',
    ];

    /**
     * The orders that belong to the product.
     */
    public function orders()
    {
        return $this->belongsToMany(Order::class);
    }

    /**
     * The pieces that belong to the product.
     */
    public function pieces()
    {
        return $this->hasMany(Piece::class);
    }

    /**
     * The warehouse entries for this product.
     */
    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }
}
