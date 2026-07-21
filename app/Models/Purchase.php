<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use CrudTrait;

    protected $fillable = [
        'product_id',
        'description',
        'quantity',
        'area',
        'file',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'area' => 'decimal:3',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function setFileAttribute($value)
    {
        $attributeName = 'file';
        $disk = 'public';
        $destinationPath = 'purchases';

        $this->uploadFileToDisk($value, $attributeName, $disk, $destinationPath);
    }
}
