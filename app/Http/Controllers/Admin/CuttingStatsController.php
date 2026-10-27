<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CuttingStats;
use Carbon\Carbon;

/**
 * Daily cutting page: m² of glass and mirror cut per product, by the day the
 * team ticked მოჭრა (see CuttingStats). Defaults to today; a from/to range
 * gives the period's totals.
 *
 * Guarded by cutting-stats.view (administrators and managers).
 */
class CuttingStatsController extends Controller
{
    public function index(CuttingStats $stats)
    {
        [$from, $to] = $this->dateRange();

        $rows = $stats->forRange($from, $to);

        $today = now()->startOfDay();

        return view('admin.cutting-stats', [
            'title' => __('warehouse.cutting.title'),
            'rows' => $rows,
            'totals' => [
                'clean' => $rows->sum('clean'),
                'offcut' => $rows->sum('offcut'),
                'total' => $rows->sum('total'),
            ],
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'presets' => [
                'today' => [$today, $today],
                'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
                'this_week' => [$today->copy()->startOfWeek(Carbon::MONDAY), $today],
                'this_month' => [$today->copy()->startOfMonth(), $today],
            ],
        ]);
    }

    /**
     * The from/to filters as days, today when missing or invalid, swapped
     * when given backwards.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    private function dateRange(): array
    {
        $parse = function (?string $value): ?Carbon {
            if (! $value) {
                return null;
            }

            try {
                return Carbon::parse($value)->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        };

        $from = $parse(request('from')) ?? now()->startOfDay();
        $to = $parse(request('to')) ?? $from->copy();

        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }
}
