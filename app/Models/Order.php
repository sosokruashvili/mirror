<?php

namespace App\Models;

use App\Services\Notifications\OrderReadySms;
use App\Services\OrderPieceStatusSync;
use App\Support\Auditing\PieceStageLogger;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
        'finish_comment',
        'expenses',
        'confirm_date',
        'due_date',
        'idempotency_key',
    ];
    // protected $hidden = [];

    protected $casts = [
        'paid' => 'boolean',
        'expenses' => 'decimal:2',
        'confirm_date' => 'datetime',
        'due_date' => 'date',
    ];

    /**
     * Stages a service order never goes through: there is no glass to cut.
     * Services that point at მოჭრა only because a stage had to be picked for
     * them count as stage-less on service orders.
     */
    public const SERVICE_ORDER_SKIPPED_STAGES = ['cutting'];

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

            // Production is done: tell the client the order can be collected.
            if ($order->status === 'ready') {
                OrderReadySms::queue($order);
            }

            // Handed out before the scheduler sent that SMS: no need for it now.
            if ($order->status === 'finished') {
                OrderReadySms::cancelPending($order);
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
     * New orders are already committed to production, so only the limitless
     * roles may delete them. Draft orders are not yet committed and may be deleted
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
            'new' => $user->isSuperAdmin(),
            default => false,
        };
    }

    /**
     * Whether the given user may edit this order, based on its status.
     *
     * Mirrors the delete rule: new orders are already committed to production,
     * so only the limitless roles may edit them. Draft orders are not yet committed
     * and may be edited by any user who otherwise holds update access (i.e.
     * role-based). Orders past the "new" stage can never be edited.
     *
     * This encodes only the status/role rule; whether the user holds the
     * "order.update" capability at all is enforced separately by the CRUD
     * access checks.
     */
    public function canBeEditedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return match ($this->status) {
            'draft' => true,
            'new' => $user->isSuperAdmin(),
            default => false,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | SERVICE ORDER STAGES
    |--------------------------------------------------------------------------
    |
    | Service orders have no pieces, so they go through the production stages
    | themselves (order_stage), the same way a piece does with piece_stage.
    | Orders with pieces never use any of this.
    */

    /**
     * Whether this order tracks its production stages on itself instead of on
     * its pieces: true only for service orders, which have no pieces.
     */
    public function tracksStagesOnOrder(): bool
    {
        if ($this->product_type !== 'service') {
            return false;
        }

        return $this->relationLoaded('pieces')
            ? $this->pieces->isEmpty()
            : ! $this->pieces()->exists();
    }

    /**
     * The stages this service order goes through, in canonical order: the
     * stages of its services plus the universal stages, minus the ones a
     * service order skips (see SERVICE_ORDER_SKIPPED_STAGES). Mirrors
     * Piece::relevantStages().
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Stage>
     */
    public function relevantStages(): Collection
    {
        $services = $this->relationLoaded('services') ? $this->services : $this->services()->get();
        $serviceStageIds = $services
            ->filter(fn ($service) => $service->pivot->piece_id === null)
            ->pluck('stage_id')
            ->filter()
            ->unique()
            ->all();

        return Stage::ordered()
            ->reject(fn (Stage $stage) => in_array($stage->name, self::SERVICE_ORDER_SKIPPED_STAGES, true))
            ->filter(fn (Stage $stage) => $stage->is_universal || in_array($stage->id, $serviceStageIds, true))
            ->values();
    }

    /**
     * The relevant stages that come before დასრულება — the real work. Empty
     * when none of the order's services has a stage of its own (e.g. only
     * სილიკონი): then there is nothing to track and nothing gates hand-out.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Stage>
     */
    public function gatingStages(): Collection
    {
        $relevant = $this->relevantStages();
        $completion = $relevant->firstWhere('name', OrderPieceStatusSync::COMPLETION_STAGE);

        if ($completion === null) {
            return collect();
        }

        return $relevant
            ->filter(fn (Stage $stage) => $stage->position < $completion->position)
            ->values();
    }

    /**
     * Slugs (names) of the stages this service order has completed.
     *
     * @return array<int, string>
     */
    public function completedStageNames(): array
    {
        return $this->stages->pluck('name')->all();
    }

    /**
     * The slug of the highest completed stage, or null when nothing is
     * completed yet. Drives the service tag colour on the team page.
     */
    public function currentStageName(): ?string
    {
        return $this->stages->sortByDesc('position')->first()?->name;
    }

    /**
     * Mark a stage completed (dated pivot row stamped with the current user)
     * or not completed (row removed). Same behaviour as
     * Piece::setStageCompleted(): completing the last gating stage auto-closes
     * დასრულება, and the order status follows.
     */
    public function setStageCompleted(Stage $stage, bool $completed): void
    {
        $has = $this->stages()->where('stages.id', $stage->id)->exists();

        if ($completed && ! $has) {
            $this->stages()->attach($stage->id, static::stageCompletionPivot());
            $this->logStageChange($stage, PieceStageLog::ACTION_COMPLETED);
        } elseif (! $completed && $has) {
            $this->stages()->detach($stage->id);
            $this->logStageChange($stage, PieceStageLog::ACTION_CLEARED);
        }

        $this->load('stages');

        // Only on completion, so deliberately clearing დასრულება sticks.
        if ($completed) {
            $this->autoCompleteFinalStage();
        }

        // The stage change came FROM an order status change (handing out):
        // the status is the input, not the result.
        if (! OrderPieceStatusSync::isSyncingFromOrder()) {
            OrderPieceStatusSync::syncOrderStatusFromPieces($this);
        }
    }

    /**
     * Close დასრულება once every gating stage is done. No team member is
     * responsible for it. Mirrors Piece::autoCompleteFinalStage().
     *
     * @return bool True when it just completed the final stage.
     */
    protected function autoCompleteFinalStage(): bool
    {
        $completion = $this->relevantStages()->firstWhere('name', OrderPieceStatusSync::COMPLETION_STAGE);
        $gatingStages = $this->gatingStages();

        if ($completion === null || $gatingStages->isEmpty()) {
            return false;
        }

        $completedIds = $this->stages->pluck('id')->all();

        if (in_array($completion->id, $completedIds, true)) {
            return false;
        }
        if (! $gatingStages->every(fn (Stage $stage) => in_array($stage->id, $completedIds, true))) {
            return false;
        }

        $this->stages()->attach($completion->id, static::stageCompletionPivot());
        $this->logStageChange($completion, PieceStageLog::ACTION_COMPLETED);
        $this->load('stages');

        return true;
    }

    /**
     * Pivot attributes stored with a stage completion: when, and by whom.
     *
     * @return array<string, mixed>
     */
    protected static function stageCompletionPivot(): array
    {
        $user = function_exists('backpack_user') ? backpack_user() : null;

        return [
            'completed_at' => now(),
            'user_id' => $user ? $user->getKey() : auth()->id(),
        ];
    }

    protected function logStageChange(Stage $stage, string $action): void
    {
        app(PieceStageLogger::class)->recordForOrder($this, $stage, $action);
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
     * The production stages this SERVICE order has completed (pivot
     * `completed_at` / `user_id`). Orders with pieces track stages on the
     * pieces instead and never have rows here.
     */
    public function stages()
    {
        return $this->belongsToMany(Stage::class, 'order_stage')
            ->withPivot('completed_at', 'user_id')
            ->withTimestamps();
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
            $this->load(['pieces' => fn ($q) => $q->withCount('brokenGlasses')]);
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
     * Glass/product portion of the order price (GEL), excluding services.
     *
     * Same formula as calculateTotalPrice()'s product loop (area × per-order
     * unit price × currency rate), without persisting piece prices. Draft
     * orders contribute nothing — they are not yet consuming warehouse stock.
     */
    public function calculateProductPriceGel(): float
    {
        if (!$this->relationLoaded('products')) {
            $this->load('products');
        }
        if (!$this->relationLoaded('pieces')) {
            $this->load('pieces');
        }

        if ($this->status === 'draft') {
            return 0.0;
        }

        $total = 0.0;
        $rate = (float) $this->currency_rate;

        foreach ($this->products as $product) {
            // Per-order pivot price (personal/manual); catalog price as fallback.
            $unitPrice = $product->pivot->price ?? $product->price;
            foreach ($this->pieces as $piece) {
                $total += round($piece->getArea() * $unitPrice * $rate, 2);
            }
        }

        return $total;
    }

    /**
     * Total order price (GEL), excluding draft pieces.
     */
    public function calculateTotalPriceExcludingDraftPieces(): float
    {
        if (!$this->relationLoaded('services')) {
            $this->load('services');
        }

        return (float) $this->services->sum('pivot.price_gel') + $this->calculateProductPriceGel();
    }

    public function calculateOrderPrice() {
        $priceGel = $this->calculateTotalPrice();
        // Order total is stored as a whole GEL amount (no decimals).
        $this->price_gel = round($priceGel);
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
                // Round to the column's own scale (pieces.price is numeric(10,2)).
                // Assigning full float precision made every save dirty forever:
                // the DB stored 11.73, the next computation produced
                // 11.731708479744002, and the two never compared equal — so each
                // call rewrote every piece and logged an audit row for a change
                // that did not exist.
                $piece->price = round($piece->getArea() * $unitPrice * $this->currency_rate, 2);
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
        
        // Display accessor (select options): read-only, must not persist.
        $price = number_format($this->calculateTotalPrice(false), 2);
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
