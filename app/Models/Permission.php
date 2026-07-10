<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use CrudTrait;

    protected $fillable = [
        'name',
        'guard_name',
        'description',
    ];

    /**
     * Human-friendly label used in admin panel selects/checklists.
     */
    public function getLabelAttribute(): string
    {
        return $this->description ?: $this->name;
    }
}
