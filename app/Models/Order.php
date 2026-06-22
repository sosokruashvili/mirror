<?php

namespace App\Models;

use App\Services\OrderPieceStatusSync;
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
        'author',
        'paid',
        'atachment',
        'comment',
        'expenses',
    ];
    // protected $hidden = [];
    
    protected $casts = [
        'paid' => 'boolean',
        'expenses' => 'decimal:2',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::updated(function (Order $order) {
            if (!$order->wasChanged('status')) {
                return;
            }

            if ($order->getOriginal('status') === 'draft' && $order->status === 'new') {
                $order->confirmDraftPieces();
            }

            if ($order->status === 'finished') {
                $order->finishAllPieces();
            }
        });
    }

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

    public static function calculateAllOrdersPrice() {
        $orders = Order::all();
        foreach($orders as $order) {
            $order->calculateOrderPrice();
        }
    }

    public function calculateExpenses(): float
    {
        if (!$this->relationLoaded('pieces')) {
            $this->load('pieces');
        }

        // Draft pieces are not yet committed to production, so they don't consume
        // any warehouse material and must be excluded from the expense.
        return round(
            $this->pieces
                ->reject(fn ($piece) => $piece->status === 'draft')
                ->sum(fn ($piece) => $piece->getExpenseArea()),
            2
        );
    }

    /**
     * Total order price (GEL), excluding draft pieces.
     */
    public function calculateTotalPriceExcludingDraftPieces(): float
    {
        if (!$this->relationLoaded('services')) {
            $this->load('services');
        }
        if (!$this->relationLoaded('products')) {
            $this->load('products');
        }
        if (!$this->relationLoaded('pieces')) {
            $this->load('pieces');
        }

        $totalPriceGel = $this->services->sum('pivot.price_gel');

        foreach ($this->products as $product) {
            foreach ($this->pieces->reject(fn ($piece) => $piece->status === 'draft') as $piece) {
                $totalPriceGel += $piece->getArea() * $product->price * $this->currency_rate;
            }
        }

        return $totalPriceGel;
    }

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

       foreach($this->products as $product) {
            foreach($this->pieces as $piece) {
                $piece->price = $piece->getArea() * $product->price * $this->currency_rate;
                $piece->save();
                $totalPriceGel += $piece->price;
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

    /**
     * Promote all draft pieces to new when the order is confirmed.
     */
    public function confirmDraftPieces(): void
    {
        OrderPieceStatusSync::withoutPieceToOrderSync(function () {
            $draftPieces = $this->pieces()->where('status', 'draft')->get();

            if ($draftPieces->isEmpty()) {
                return;
            }

            foreach ($draftPieces as $piece) {
                $piece->status = 'new';
                $piece->saveQuietly();
            }

            $this->expenses = $this->calculateExpenses();
            $this->saveQuietly();
        });
    }

    /**
     * Mark all pieces as finished when the order is finished.
     */
    public function finishAllPieces(): void
    {
        OrderPieceStatusSync::withoutPieceToOrderSync(function () {
            if (!$this->relationLoaded('pieces')) {
                $this->load('pieces');
            }

            foreach ($this->pieces as $piece) {
                if ($piece->status === 'finished') {
                    continue;
                }

                $piece->status = 'finished';
                $piece->saveQuietly();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    public function setAtachmentAttribute($value)
    {
        $attributeName = 'atachment';
        $disk = 'public';
        $destinationPath = 'orders/attachments';

        $this->uploadFileToDisk($value, $attributeName, $disk, $destinationPath);
    }
}
