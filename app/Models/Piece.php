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
     * in `pieces.stage`). `pieces.stage` is a denormalized cache of the highest
     * completed stage — see {@see refreshStageColumn()} and the stages() pivot.
     */
    public function stageModel()
    {
        return $this->belongsTo(Stage::class, 'stage', 'name');
    }

    /**
     * The services attached to this piece (order_service rows carrying this
     * piece's id). A piece's selectable/relevant production stages are the
     * stages of these services plus the universal stages.
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'order_service', 'piece_id', 'service_id')
            ->withTimestamps();
    }

    /**
     * The production stages this piece has COMPLETED, each with the datetime it
     * was completed (pivot `completed_at`). This is the authoritative record of
     * the piece's progress; `pieces.stage` is just a cache derived from it.
     */
    public function stages()
    {
        return $this->belongsToMany(Stage::class, 'piece_stage')
            ->withPivot('completed_at')
            ->withTimestamps();
    }

    /**
     * The completed stages as a loaded collection (uses the eager-loaded
     * relation when available to avoid an extra query).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Stage>
     */
    protected function completedStages()
    {
        return $this->relationLoaded('stages') ? $this->stages : $this->stages()->get();
    }

    /**
     * Slugs (names) of the stages this piece has completed.
     *
     * @return array<int, string>
     */
    public function completedStageNames(): array
    {
        return $this->completedStages()->pluck('name')->all();
    }

    /**
     * The completed stage with the highest position (the piece's furthest point
     * of progress), or null when nothing is completed yet.
     */
    public function highestCompletedStage(): ?Stage
    {
        return $this->completedStages()->sortByDesc('position')->first();
    }

    /**
     * Recompute the denormalized `pieces.stage` cache from the completed-stage
     * pivot and persist it when it changed. Saving flips the `stage` column,
     * which fires PieceObserver → order-status sync, exactly as before.
     */
    public function refreshStageColumn(): void
    {
        $slug = $this->highestCompletedStage()?->name;

        if ($this->stage !== $slug) {
            $this->stage = $slug;
            $this->save();
        }
    }

    /**
     * Mark a single stage completed (creating a dated pivot record) or not
     * completed (removing it), preserving the completion time of stages that
     * were already done. Used by the team page checkbox toggles.
     */
    public function setStageCompleted(Stage $stage, bool $completed): void
    {
        $has = $this->stages()->where('stages.id', $stage->id)->exists();

        if ($completed && !$has) {
            $this->stages()->attach($stage->id, ['completed_at' => now()]);
        } elseif (!$completed && $has) {
            $this->stages()->detach($stage->id);
        }

        $this->load('stages');
        $this->refreshStageColumn();
    }

    /**
     * Mark every stage up to and including the given one as completed (and
     * detach any completed stage beyond it), preserving existing completion
     * times. Passing null clears all completion. Used by the single-select
     * admin stage editor, whose "piece is at stage X" means "completed through X".
     */
    public function setCompletedThroughStage(?Stage $stage): void
    {
        if ($stage === null) {
            $this->stages()->detach();
        } else {
            $completedIds = $this->stages()->pluck('stages.id')->all();
            $throughPos = $stage->position;

            $detach = [];
            $attach = [];
            foreach (Stage::ordered() as $s) {
                if ($s->position > $throughPos) {
                    if (in_array($s->id, $completedIds, true)) {
                        $detach[] = $s->id;
                    }
                } elseif (!in_array($s->id, $completedIds, true)) {
                    $attach[$s->id] = ['completed_at' => now()];
                }
            }

            if (!empty($detach)) {
                $this->stages()->detach($detach);
            }
            if (!empty($attach)) {
                $this->stages()->attach($attach);
            }
        }

        $this->load('stages');
        $this->refreshStageColumn();
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
