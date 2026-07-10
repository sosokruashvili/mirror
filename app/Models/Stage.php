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
    ];

    protected $casts = [
        'position' => 'integer',
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
     * Pieces currently at this stage (matched by the `name` slug).
     */
    public function pieces()
    {
        return $this->hasMany(Piece::class, 'stage', 'name');
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
