<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Currency;

class Service extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $table = 'services'; 
    protected $guarded = ['id'];

    protected $fillable = [
        'title',
        'slug',
        'description',
        'unit',
        'price',
        'price_gel',
        'extra_field_names',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_gel' => 'decimal:2',
        'extra_field_names' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * The orders that belong to the service.
     */
    public function orders()
    {
        return $this->belongsToMany(Order::class)
            ->withPivot('quantity', 'description')
            ->withTimestamps();
    }

    public function getPriceGel()
    {
        if(!$this->price_gel && !$this->price) {
            return false;
        }
        return ($this->price_gel) ? $this->price_gel : $this->price * Currency::exchangeRate();
    }
}
