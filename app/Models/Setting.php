<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A single global application setting (key => value), edited on the Global
 * Settings page. `type` drives how the value is rendered/validated and how it
 * is cast when read back through the setting() helper.
 */
class Setting extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
        'type',
        'label',
        'description',
        'group',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public const CACHE_KEY = 'settings.all';

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetCache());
        static::deleted(fn () => static::forgetCache());
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Display label in the active locale.
     *
     * Settings rows are seeded with English label/description/group text. Those
     * are translated by key through the setting lang file when an entry exists,
     * and otherwise fall back to whatever is stored in the database - so adding
     * a new setting never leaves a blank label.
     */
    public function translatedLabel(): string
    {
        return static::translateOrFallback('setting.labels.' . $this->key, (string) $this->label);
    }

    /**
     * Display description in the active locale, or null when there is none.
     */
    public function translatedDescription(): ?string
    {
        if (blank($this->description)) {
            return null;
        }

        return static::translateOrFallback('setting.descriptions.' . $this->key, (string) $this->description);
    }

    /**
     * Display name for a settings group ("General" when the row has none).
     */
    public static function translatedGroup(?string $group): string
    {
        if (blank($group)) {
            return __('setting.general_group');
        }

        return static::translateOrFallback('setting.groups.' . $group, $group);
    }

    /**
     * __() returns the key itself when there is no translation; treat that as
     * "not translated" and use the database text instead.
     */
    protected static function translateOrFallback(string $key, string $fallback): string
    {
        $translated = __($key);

        return is_string($translated) && $translated !== $key ? $translated : $fallback;
    }

    /**
     * All settings keyed by their `key`, ordered for display, cached for the
     * request lifetime. Falls back to an empty collection if the table does
     * not exist yet (e.g. before migrations run).
     *
     * @return \Illuminate\Database\Eloquent\Collection<string, static>
     */
    public static function allKeyed(): Collection
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                return static::query()
                    ->orderBy('group')
                    ->orderBy('position')
                    ->orderBy('id')
                    ->get()
                    ->keyBy('key');
            });
        } catch (\Throwable $e) {
            return new Collection();
        }
    }

    /**
     * Read a setting's value cast to its declared type, or $default when the
     * setting is missing or has no value stored.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::allKeyed()->get($key);

        if ($setting === null || $setting->value === null || $setting->value === '') {
            return $default;
        }

        return $setting->typedValue();
    }

    /**
     * Persist a value for the given key (no-op if the key doesn't exist).
     */
    public static function put(string $key, mixed $value): void
    {
        static::query()->where('key', $key)->update(['value' => $value]);
        static::forgetCache();
    }

    /**
     * This setting's value cast according to its `type`.
     */
    public function typedValue(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => (bool) $this->value,
            'float' => (float) $this->value,
            default => $this->value,
        };
    }
}
