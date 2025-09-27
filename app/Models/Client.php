<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $table = 'clients';
    protected $guarded = ['id'];
    
    protected $fillable = [
        'name',
        'email',
        'client_type',
        'personal_id',
        'legal_id',
        'address',
        'phone_number'
    ];
    
    protected $casts = [
        'client_type' => 'boolean'
    ];

    /**
     * The orders that belong to the client.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
