<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use CrudTrait;

    protected $fillable = [
        'product_id',
        'supplier_id',
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

    /**
     * The supplier the material was bought from. Optional — older rows and
     * stock of unknown origin have none.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
