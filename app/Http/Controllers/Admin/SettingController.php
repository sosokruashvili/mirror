<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Prologue\Alerts\Facades\Alert;

/**
 * Single-page editor for global application settings.
 *
 * Settings live in the `settings` table (one row per parameter). The form is
 * data-driven: each setting renders an input based on its `type`, and values
 * are read elsewhere through the setting() helper.
 */
class SettingController extends Controller
{
    /**
     * Show the settings form, with settings grouped for display.
     *
     * @return \Illuminate\View\View
     */
    public function edit()
    {
        $settings = Setting::allKeyed()->values()->groupBy('group');

        return view('admin.settings', [
            'settingGroups' => $settings,
            'dbSyncAvailable' => $this->isDbSyncAvailable(),
            'dbSyncSource' => config('dbsync.source.database'),
        ]);
    }

    /**
     * Replace the current (dev) database with a fresh copy of production.
     *
     * Only ever available on dev: the underlying command refuses to run when
     * the current database is the same as the configured source, and the
     * button is hidden on production for the same reason.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function syncFromProd()
    {
        if (! $this->isDbSyncAvailable()) {
            Alert::error('Database sync is not available in this environment.')->flash();

            return redirect()->route('settings.edit');
        }

        $exitCode = Artisan::call('db:sync-from-prod', ['--force' => true]);
        $output = trim(Artisan::output());

        if ($exitCode === 0) {
            Alert::success('Database synced from production.')->flash();
        } else {
            Alert::error('Database sync failed: ' . $output)->flash();
        }

        return redirect()->route('settings.edit');
    }

    /**
     * DB sync is available only when the current database differs from the
     * configured production source (i.e. we are on dev, not prod).
     */
    private function isDbSyncAvailable(): bool
    {
        $default = config('database.default');
        $current = config('database.connections.' . $default);

        return ($current['driver'] ?? null) === 'pgsql'
            && ! empty($current['database'])
            && ! empty(config('dbsync.source.database'))
            && $current['database'] !== config('dbsync.source.database');
    }

    /**
     * Validate and persist submitted setting values.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        $settings = Setting::allKeyed();

        $rules = [];
        $attributes = [];
        foreach ($settings as $setting) {
            $rules['settings.' . $setting->key] = $this->rulesForType($setting->type);
            $attributes['settings.' . $setting->key] = $setting->label;
        }

        $validated = $request->validate($rules, [], $attributes);
        $values = $validated['settings'] ?? [];

        foreach ($settings as $setting) {
            if (array_key_exists($setting->key, $values)) {
                Setting::put($setting->key, $values[$setting->key]);
            }
        }

        Alert::success('Settings saved.')->flash();

        return redirect()->route('settings.edit');
    }

    /**
     * Validation rules for a given setting type. Values are optional so a
     * setting can be left blank.
     *
     * @param string $type
     * @return array<int, mixed>
     */
    private function rulesForType(string $type): array
    {
        return match ($type) {
            'integer' => ['nullable', 'integer', 'min:0'],
            'float' => ['nullable', 'numeric'],
            'boolean' => ['nullable', 'boolean'],
            default => ['nullable', 'string', 'max:65535'],
        };
    }
}
