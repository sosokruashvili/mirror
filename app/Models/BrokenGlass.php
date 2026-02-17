<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrokenGlass extends Model
{
    protected $table = 'broken_glasses';

    protected $fillable = [
        'piece_id',
        'description',
    ];

    /**
     * The piece this broken glass record belongs to.
     */
    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }
}
