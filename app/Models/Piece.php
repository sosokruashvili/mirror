<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Piece extends Model
{
    use CrudTrait;

    protected $fillable = [
        'quantity',
        'order_id',
        'product_id',
        'width',
        'height',
        'stage',
        'broken',
    ];

    protected $casts = [
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'broken' => 'integer',
    ];

    /**
     * The order that owns the piece.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The product that owns the piece.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The production stage this piece is at (matched by the `name` slug stored
     * in `pieces.stage`).
     */
    public function stageModel()
    {
        return $this->belongsTo(Stage::class, 'stage', 'name');
    }

    /**
     * Broken glass records for this piece (with optional description).
     */
    public function brokenGlasses()
    {
        return $this->hasMany(BrokenGlass::class);
    }

    /**
     * Area of a single sheet of this piece (m²), ignoring quantity.
     */
    public function getUnitArea()
    {
        return $this->width / 100 * $this->height / 100;
    }

    public function getArea()
    {
        return $this->getUnitArea() * $this->quantity;
    }

    /**
     * Number of times this piece has been broken.
     */
    public function getBrokenCount(): int
    {
        $recordCount = $this->relationLoaded('brokenGlasses')
            ? $this->brokenGlasses->count()
            : $this->brokenGlasses()->count();

        return max($recordCount, (int) ($this->broken ?? 0));
    }

    /**
     * Total area (m²) consumed from the warehouse for this piece, including
     * an extra sheet for every broken record.
     */
    public function getExpenseArea()
    {
        return $this->getUnitArea() * ($this->quantity + $this->getBrokenCount());
    }

    public function servicesShortnames()
    {
        return $this->services->pluck('shortname')->unique()->implode(', ');
    }

    /**
     * Georgian label for the piece's production stage.
     */
    public function getStageLabelAttribute(): string
    {
        return piece_stage_ge($this->stage);
    }
}
