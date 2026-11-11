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
        'shortname',
        'slug',
        'stage_id',
        'description',
        'unit',
        'price',
        'price_gel',
        'cutloss',
        'extra_field_names',
        'companion_service_ids',
        'product_types',
    ];

    /**
     * Order product types a service can be limited to, in the order the matrix
     * page shows them. Labels come from lang/{locale}/product_type.php.
     */
    public const PRODUCT_TYPES = ['glass', 'mirror', 'glass_pkg', 'lamix', 'service'];

    protected $casts = [
        'price' => 'decimal:2',
        'price_gel' => 'decimal:2',
        'cutloss' => 'integer',
        'extra_field_names' => 'array',
        'companion_service_ids' => 'array',
        'product_types' => 'array',
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

    /**
     * The production stage this service belongs to.
     */
    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * Services the order form adds next to this one when it is picked
     * (e.g. განათება → ქაფი, ასაწყობი მასალა, საკიდი). Each is still saved as its
     * own order line, so stats and the invoice count it separately.
     *
     * @return list<int>
     */
    public function companionServiceIds(): array
    {
        $ids = $this->companion_service_ids;

        return is_array($ids) ? self::normalizeIds($ids, $this->id) : [];
    }

    /**
     * Store companions as unique integer ids, never the service itself; empty → null.
     */
    public function setCompanionServiceIdsAttribute($value): void
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $ids = is_array($value) ? self::normalizeIds($value, $this->id) : [];

        $this->attributes['companion_service_ids'] = $ids === [] ? null : json_encode($ids);
    }

    /**
     * The order product types this service is limited to; [] means every type.
     *
     * @return list<string>
     */
    public function productTypes(): array
    {
        $types = $this->product_types;

        return is_array($types) ? self::normalizeProductTypes($types) : [];
    }

    /**
     * Whether the order form offers this service on an order of $productType.
     * A service with no types ticked is offered everywhere, and so is every
     * service while the order has no type yet.
     */
    public function isOfferedFor(?string $productType): bool
    {
        $types = $this->productTypes();

        return $types === [] || $productType === null || $productType === ''
            || in_array($productType, $types, true);
    }

    /**
     * Store known product types only, unique and in PRODUCT_TYPES order; empty → null.
     */
    public function setProductTypesAttribute($value): void
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $types = is_array($value) ? self::normalizeProductTypes($value) : [];

        $this->attributes['product_types'] = $types === [] ? null : json_encode($types);
    }

    /**
     * @param  array<int, mixed>  $types
     * @return list<string>
     */
    private static function normalizeProductTypes(array $types): array
    {
        $given = array_map('strval', array_filter($types, 'is_scalar'));

        return array_values(array_filter(self::PRODUCT_TYPES, fn (string $t) => in_array($t, $given, true)));
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    private static function normalizeIds(array $ids, $selfId): array
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && $id !== (int) $selfId && ! in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }

        return $clean;
    }

    public function getPriceGel()
    {
        if(!$this->price_gel && !$this->price) {
            return false;
        }
        return ($this->price_gel) ? $this->price_gel : $this->price * Currency::exchangeRate();
    }
}
