<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use CrudTrait;
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'orders';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
        'status',
        'order_type',
        'client_id',
        'currency_rate',
        'product_type',
        'author'
    ];
    // protected $hidden = [];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * The products that belong to the order.
     */
    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * The pieces that belong to the order.
     */
    public function pieces()
    {
        return $this->hasMany(Piece::class);
    }

    /**
     * The services that belong to the order.
     */
    public function services()
    {
        return $this->belongsToMany(Service::class)
            ->withPivot(
                'quantity', 
                'description', 
                'color', 
                'light_type', 
                'price_gel', 
                'distance', 
                'length_cm', 
                'perimeter', 
                'area', 
                'foam_length', 
                'tape_length', 
                'sensor_type',
                'sensor_quantity1',
                )->withTimestamps();
    }

    /**
     * The client that owns the order.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function calculateTotalPrice()
    {
        $totalPriceGel = 0;
        if($this->product_type != 'service') {
            foreach($this->pieces as $piece) {

                foreach($this->products as $product) {  
                    $totalPriceGel += $piece->getArea() * $product->price * $this->currency_rate;
                }

            }

            foreach($this->services as $service) {
                $totalPriceGel += $service->pivot->price_gel;
            }
        }
        return 0;
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
