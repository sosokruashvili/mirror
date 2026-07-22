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
        'author',
        'paid',
        'atachment',
        'comment',
        'expenses',
        'confirm_date',
    ];
    // protected $hidden = [];

    protected $casts = [
        'paid' => 'boolean',
        'expenses' => 'decimal:2',
        'confirm_date' => 'datetime',
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

            // A piece counts as "draft" (excluded from expenses/pricing) purely by
            // its order being in draft. When the order leaves draft, its pieces
            // become production pieces, so the expense must be recalculated.
            if ($order->getOriginal('status') === 'draft' && $order->status !== 'draft') {
                $order->expenses = $order->calculateExpenses();
                $order->saveQuietly();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the given user may delete this order, based on its status.
     *
     * New orders are already committed to production, so only administrators
     * may delete them. Draft orders are not yet committed and may be deleted
     * by any user who otherwise holds delete access (e.g. operators). Orders
     * past the "new" stage can never be deleted.
     *
     * This encodes only the status/role rule; whether the user holds the
     * "order.delete" capability at all is enforced separately by the CRUD
     * access checks.
     */
    public function canBeDeletedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return match ($this->status) {
            'draft' => true,
            'new' => $user->hasRole('admin'),
            default => false,
        };
    }

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
     * The user who created the order (stored on orders.author).
     *
     * Named authorUser() rather than author() on purpose: a relationship method
     * that shares its name with the real `author` column collides, and Eloquent
     * returns the raw column value instead of loading the relation.
     */
    public function authorUser()
    {
        return $this->belongsTo(User::class, 'author');
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

    /**
     * Total amount (GEL) of payments directly linked to this order (via payments.order_id).
     * Sums every linked payment regardless of status (Paid/Pending).
     */
    public function calculatePaidAmount(): float
    {
        if (!$this->relationLoaded('payments')) {
            $this->load('payments');
        }

        return (float) $this->payments->sum('amount_gel');
    }

    public function calculateExpenses(): float
    {
        if ($this->status === 'draft') {
            return 0.0;
        }

        return round(
            $this->calculateBaseExpenses() + $this->calculateOffcutArea(),
            2
        );
    }

    /**
     * Piece area (m²) consumed before applying product offcut %.
     * Draft orders contribute nothing.
     */
    public function calculateBaseExpenses(): float
    {
        if (!$this->relationLoaded('pieces')) {
            $this->load('pieces');
        }

        if ($this->status === 'draft') {
            return 0.0;
        }

        return (float) $this->pieces->sum(fn ($piece) => $piece->getExpenseArea());
    }

    /**
     * Highest offcut % among products on this order (0 when none).
     * Multi-product orders (lamix / glass_pkg) share one sheet area, so a
     * single offcut rate is applied — the max across selected products.
     */
    public function getOffcutPercent(): float
    {
        if (!$this->relationLoaded('products')) {
            $this->load('products');
        }

        if ($this->products->isEmpty()) {
            return 0.0;
        }

        return (float) $this->products->max(fn ($product) => (float) ($product->offcut ?? 0));
    }

    /**
     * Extra area (m²) from product offcut % applied to base piece area.
     */
    public function calculateOffcutArea(): float
    {
        $percent = $this->getOffcutPercent();
        if ($percent <= 0) {
            return 0.0;
        }

        return round($this->calculateBaseExpenses() * ($percent / 100), 2);
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

        // Draft orders exclude their (draft) pieces from the price; only services count.
        if ($this->status !== 'draft') {
            foreach ($this->products as $product) {
                // Use the per-order price stored on the pivot (personal/manually entered
                // price); fall back to the catalog price only when no pivot price is set.
                $unitPrice = $product->pivot->price ?? $product->price;
                foreach ($this->pieces as $piece) {
                    $totalPriceGel += $piece->getArea() * $unitPrice * $this->currency_rate;
                }
            }
        }

        return $totalPriceGel;
    }

    public function calculateOrderPrice() {
        $priceGel = $this->calculateTotalPrice();
        $this->price_gel = $priceGel;
        $this->save();
    }

    /**
     * @param  bool  $persist  Whether to also write each piece's computed price back
     *                         to the piece. Pass false on read-only screens, which
     *                         only need the number and shouldn't write on a GET.
     */
    public function calculateTotalPrice($persist = true)
    {
       $totalPriceGel = 0;
       $servicePriceSum = $this->services->sum('pivot.price_gel');
       $totalPriceGel += $servicePriceSum;

       foreach($this->products as $product) {
            // Use the per-order price stored on the pivot (personal/manually entered
            // price); fall back to the catalog price only when no pivot price is set.
            $unitPrice = $product->pivot->price ?? $product->price;
            foreach($this->pieces as $piece) {
                $piece->price = $piece->getArea() * $unitPrice * $this->currency_rate;
                if ($persist) {
                    $piece->save();
                }
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
