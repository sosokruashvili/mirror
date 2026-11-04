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
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_gel' => 'decimal:2',
        'cutloss' => 'integer',
        'extra_field_names' => 'array',
        'companion_service_ids' => 'array',
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
