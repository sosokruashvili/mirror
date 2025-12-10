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
        return $this->belongsToMany(Product::class)->withPivot('price');
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
                'piece_id',
                )->withTimestamps();
    }

    /**
     * The client that owns the order.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The payments that belong to the order.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
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

    public function calculateOrderPrice() {
        $priceGel = $this->calculateTotalPrice();
        $this->price_gel = $priceGel;
        $this->save();
    }

    public function calculateTotalPrice()
    {
       $totalPriceGel = 0; 
       $servicePriceSum = $this->services->sum('pivot.price_gel');
       $totalPriceGel += $servicePriceSum;

       if($this->order_type == 'retail') {
            foreach($this->products as $product) {
                foreach($this->pieces as $piece) {
                    $totalPriceGel += $piece->getArea() * $product->price * $this->currency_rate;
                }
            }
       }
       return $totalPriceGel;
    }

    /**
     * Get the display name for select options.
     * Shows: Order ID - Product Type - Price (GEL)
     */
    public function getOrderDisplayAttribute()
    {
        // Ensure relationships are loaded for price calculation
        if (!$this->relationLoaded('services')) {
            $this->load('services');
        }
        if (!$this->relationLoaded('products')) {
            $this->load('products');
        }
        if (!$this->relationLoaded('pieces')) {
            $this->load('pieces');
        }
        
        $price = number_format($this->calculateTotalPrice(), 2);
        $productType = product_type_ge($this->product_type ?? '');
        return "Order #{$this->id} - {$productType} - {$price} ₾";
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
