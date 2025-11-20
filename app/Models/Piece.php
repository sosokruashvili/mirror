<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Piece extends Model
{
    use CrudTrait;

    protected $fillable = [
        'quantity',
        'order_id',
        'product_id',
        'width',
        'height',
        'status',
    ];

    protected $casts = [
        'width' => 'decimal:2',
        'height' => 'decimal:2',
    ];

    /**
     * The order that owns the piece.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The product that owns the piece.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getArea()
    {
        return $this->width/1000 * $this->height/1000 * $this->quantity;
    }
}
