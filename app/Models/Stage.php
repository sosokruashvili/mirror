<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A production stage a piece goes through (მოჭრა → დასრულება).
 *
 * `name` is the machine identifier stored on `pieces.stage`; `title` is the
 * Georgian display label; `color` is the badge color; `position` orders the
 * stages everywhere they are listed.
 */
class Stage extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $table = 'stages';

    protected $fillable = [
        'name',
        'title',
        'color',
        'position',
        'is_universal',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_universal' => 'boolean',
    ];

    public const CACHE_KEY = 'stages.ordered';

    protected static function booted(): void
    {
        // Keep the cached ordered list (used by the piece_stage* helpers) fresh.
        static::saved(fn () => static::forgetCache());
        static::deleted(fn () => static::forgetCache());
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * All stages ordered by position (then id), cached for the request lifetime.
     * Falls back to an empty collection if the table does not exist yet
     * (e.g. before migrations run).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function ordered(): Collection
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                return static::query()
                    ->orderBy('position')
                    ->orderBy('id')
                    ->get();
            });
        } catch (\Throwable $e) {
            return new Collection();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Services assigned to this stage.
     */
    public function services()
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Pieces that have COMPLETED this stage, with the completion time on the
     * pivot (`completed_at`).
     */
    public function pieces()
    {
        return $this->belongsToMany(Piece::class, 'piece_stage')
            ->withPivot('completed_at', 'user_id')
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Store colors lowercased so they satisfy the color field's `#[0-9a-f]{6}`
     * pattern. Without this an uppercase value (e.g. #10B981) trips the HTML5
     * validation ("Please match the requested format") when the edit form
     * reloads with the stored value.
     */
    public function setColorAttribute($value): void
    {
        $this->attributes['color'] = is_string($value) ? strtolower($value) : $value;
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    /**
     * Readable text color (dark/white) to place on top of this stage's color.
     */
    public function getTextColorAttribute(): string
    {
        return stage_contrast_text_color($this->color);
    }
}
