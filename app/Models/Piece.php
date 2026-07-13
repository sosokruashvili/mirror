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
     * was completed (pivot `completed_at`) and the user who marked it complete
     * (pivot `user_id`). This is the authoritative record of the piece's progress.
     */
    public function stages()
    {
        return $this->belongsToMany(Stage::class, 'piece_stage')
            ->withPivot('completed_at', 'user_id')
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
     * The production stages relevant to this piece, in canonical order: the
     * universal stages (that apply to every piece) plus the stages of the
     * services attached to it. This mirrors the selectable set on the team
     * orders page — the stages the piece must pass through.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Stage>
     */
    public function relevantStages(): \Illuminate\Support\Collection
    {
        $services = $this->relationLoaded('services') ? $this->services : $this->services()->get();
        $serviceStageIds = $services->pluck('stage_id')->filter()->unique()->all();

        return Stage::ordered()
            ->filter(fn (Stage $stage) => $stage->is_universal || in_array($stage->id, $serviceStageIds, true))
            ->values();
    }

    /**
     * Auto-close the final 'completion' (დასრულება) stage once every other
     * stage relevant to this piece has been completed. No team member is
     * responsible for that stage, so it should pass automatically as soon as
     * the real production work is done.
     *
     * Attaches straight to the pivot (not via setStageCompleted) so it does not
     * re-trigger this check. Callers gate it to "completing" actions so a
     * deliberate uncheck of 'completion' is not immediately undone.
     *
     * @return bool True when it just completed the final stage.
     */
    protected function autoCompleteFinalStage(): bool
    {
        $relevant = $this->relevantStages();

        $completion = $relevant->firstWhere('name', 'completion');
        if ($completion === null) {
            return false;
        }

        // The stages that gate completion — everything relevant except the
        // final stage itself. Nothing to gate on → don't auto-complete.
        $gatingStages = $relevant->reject(fn (Stage $stage) => $stage->name === 'completion');
        if ($gatingStages->isEmpty()) {
            return false;
        }

        $completedIds = $this->completedStages()->pluck('id')->all();

        // Already done, or a gating stage is still outstanding: nothing to do.
        if (in_array($completion->id, $completedIds, true)) {
            return false;
        }
        if (!$gatingStages->every(fn (Stage $stage) => in_array($stage->id, $completedIds, true))) {
            return false;
        }

        $this->stages()->attach($completion->id, static::completionPivot());
        $this->load('stages');

        return true;
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
     * The slug of the piece's highest completed stage (its furthest point of
     * progress), or null when nothing is completed yet.
     */
    public function currentStageName(): ?string
    {
        return $this->highestCompletedStage()?->name;
    }

    /**
     * Resync the owning order's status from its pieces' completed stages. Called
     * after the completed-stage pivot changes (there is no `stage` column to key
     * a model observer off anymore).
     */
    public function syncOrderStatus(): void
    {
        if ($order = $this->order) {
            \App\Services\OrderPieceStatusSync::syncOrderStatusFromPieces($order);
        }
    }

    /**
     * The id of the user performing the current stage update (recorded on the
     * piece_stage pivot), or null outside an authenticated request.
     */
    protected static function currentActorId(): ?int
    {
        if (function_exists('backpack_user') && ($user = backpack_user())) {
            return $user->getKey();
        }

        return auth()->id();
    }

    /**
     * Pivot attributes stored when a stage completion is recorded: the time and
     * the user who did it.
     *
     * @return array<string, mixed>
     */
    protected static function completionPivot(): array
    {
        return [
            'completed_at' => now(),
            'user_id' => static::currentActorId(),
        ];
    }

    /**
     * Mark a single stage completed (creating a dated pivot record stamped with
     * the current user) or not completed (removing it), preserving the
     * completion time of stages that were already done. Used by the team page
     * checkbox toggles.
     */
    public function setStageCompleted(Stage $stage, bool $completed): void
    {
        $has = $this->stages()->where('stages.id', $stage->id)->exists();

        if ($completed && !$has) {
            $this->stages()->attach($stage->id, static::completionPivot());
        } elseif (!$completed && $has) {
            $this->stages()->detach($stage->id);
        }

        $this->load('stages');

        // Completing a stage may have been the last one gating 'completion'.
        // Only run on completion (not on an uncheck) so deliberately clearing
        // the final stage isn't instantly undone.
        if ($completed) {
            $this->autoCompleteFinalStage();
        }

        $this->syncOrderStatus();
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
            $pivot = static::completionPivot();
            foreach (Stage::ordered() as $s) {
                if ($s->position > $throughPos) {
                    if (in_array($s->id, $completedIds, true)) {
                        $detach[] = $s->id;
                    }
                } elseif (!in_array($s->id, $completedIds, true)) {
                    $attach[$s->id] = $pivot;
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

        // "Completed through X" that reaches the last gating stage should close
        // the final 'completion' stage too. Skip when clearing all (stage null).
        if ($stage !== null) {
            $this->autoCompleteFinalStage();
        }

        $this->syncOrderStatus();
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
}
