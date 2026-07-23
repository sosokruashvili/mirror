<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class WarehouseSnapshot extends Model
{
    use CrudTrait;

    protected $fillable = [
        'product_id',
        'snapshot_date',
        'warehouse_area',
        'expenses',
        'remaining',
        'offcut_percent',
        'offcut_area',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'warehouse_area' => 'decimal:3',
        'expenses' => 'decimal:3',
        'remaining' => 'decimal:3',
        'offcut_percent' => 'decimal:2',
        'offcut_area' => 'decimal:3',
    ];

    /**
     * The product this warehouse snapshot belongs to.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
