<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use CrudTrait;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'guard_name',
    ];
}
