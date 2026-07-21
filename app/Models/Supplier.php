<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use CrudTrait;

    protected $fillable = [
        'name',
        'description',
        'email',
    ];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}
