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
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // After a piece is updated, check if all pieces of the order are ready
        static::updated(function ($piece) {
            // Only check if status was changed
            if ($piece->wasChanged('status') && $piece->order) {
                $piece->order->updateStatusIfAllPiecesReady();
            }
        });
    }

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
        return $this->width/100 * $this->height/100 * $this->quantity;
    }
}
