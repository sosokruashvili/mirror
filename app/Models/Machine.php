<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A workshop machine a glass can break on (კრომკა, ფასკა, საჭრელი მაგიდა …).
 *
 * The list is picked from in the team's "გატყდა" dialog and is edited by admins
 * on the Machines page. A machine is switched off (is_active = false) instead of
 * deleted, because broken_glasses rows keep pointing at it.
 */
class Machine extends Model
{
    use CrudTrait;

    protected $fillable = [
        'name',
        'position',
        'requires_comment',
        'is_active',
    ];

    protected $casts = [
        'position' => 'integer',
        'requires_comment' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function brokenGlasses(): HasMany
    {
        return $this->hasMany(BrokenGlass::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }
}
