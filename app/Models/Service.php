<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $table = 'services'; 
    protected $guarded = ['id'];

    protected $fillable = [
        'title',
        'description',
        'unit',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
