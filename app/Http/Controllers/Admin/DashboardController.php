<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController
{
    /**
     * Orders area chart data grouped by day, month, or year.
     * Excludes draft orders and draft pieces from all totals.
     */
    public function getOrdersAreaChart(Request $request): JsonResponse
    {
        $period = $request->query('period', 'days');
        if (! in_array($period, ['days', 'months', 'years'], true)) {
            $period = 'days';
        }

        return response()->json($this->buildOrdersAreaChartData($period));
    }

    /**
     * @return array{period: string, labels: array<int, string>, areas: array<int, float>, totalArea: float}
     */
    private function buildOrdersAreaChartData(string $period): array
    {
        $areaExpression = '(pieces.width / 100.0) * (pieces.height / 100.0) * pieces.quantity';

        $periodConfig = match ($period) {
            'months' => [
                'start' => now()->subMonths(11)->startOfMonth(),
                'count' => 12,
                'step' => 'month',
                'sqlGroup' => "DATE_TRUNC('month', orders.created_at)",
                'labelFormat' => 'M Y',
            ],
            'years' => [
                'start' => now()->subYears(9)->startOfYear(),
                'count' => 10,
                'step' => 'year',
                'labelFormat' => 'Y',
            ],
            default => [
                'start' => now()->subDays(29)->startOfDay(),
                'count' => 30,
                'step' => 'day',
                'sqlGroup' => 'orders.created_at::date',
                'labelFormat' => 'd M',
            ],
        };

        $sqlGroup = $periodConfig['sqlGroup'] ?? "DATE_TRUNC('{$periodConfig['step']}', orders.created_at)";

        $rows = DB::table('orders')
            ->join('pieces', 'pieces.order_id', '=', 'orders.id')
            ->where('orders.status', '!=', 'draft')
            ->where('orders.created_at', '>=', $periodConfig['start'])
            ->selectRaw("{$sqlGroup} as period_key, SUM({$areaExpression}) as total_area")
            ->groupByRaw('period_key')
            ->orderBy('period_key')
            ->get();

        $areasByKey = [];
        foreach ($rows as $row) {
            $key = $this->normalizePeriodKey($row->period_key, $periodConfig['step']);
            $areasByKey[$key] = round((float) $row->total_area, 2);
        }

        $labels = [];
        $areas = [];
        $cursor = $periodConfig['start']->copy();

        for ($i = 0; $i < $periodConfig['count']; $i++) {
            $key = $this->periodKey($cursor, $periodConfig['step']);
            $labels[] = $cursor->format($periodConfig['labelFormat']);
            $areas[] = $areasByKey[$key] ?? 0.0;

            match ($periodConfig['step']) {
                'month' => $cursor->addMonth(),
                'year' => $cursor->addYear(),
                default => $cursor->addDay(),
            };
        }

        return [
            'period' => $period,
            'labels' => $labels,
            'areas' => $areas,
            'totalArea' => round(array_sum($areas), 2),
        ];
    }

    private function normalizePeriodKey(mixed $value, string $step): string
    {
        if ($value instanceof Carbon) {
            return $this->periodKey($value, $step);
        }

        return $this->periodKey(Carbon::parse($value), $step);
    }

    private function periodKey(Carbon $date, string $step): string
    {
        return match ($step) {
            'month' => $date->copy()->startOfMonth()->format('Y-m-d'),
            'year' => $date->copy()->startOfYear()->format('Y-m-d'),
            default => $date->copy()->startOfDay()->format('Y-m-d'),
        };
    }
}
