<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController
{
    /**
     * Per-day order statistics for the daily stats bar chart.
     *
     * Returns, for each day in the requested range, the number of confirmed
     * orders and the income for that day split into the paid portion and the
     * outstanding (credit/owed) portion. Draft orders are excluded.
     *
     * The date range defaults to the last 30 days and can be overridden with
     * `from` / `to` query params (YYYY-MM-DD). The range is capped at 366 days
     * to keep the in-memory price calculation bounded.
     */
    public function getDailyStatsChart(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveDailyStatsRange($request);

        $orders = Order::query()
            ->where('status', '!=', 'draft')
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->with(['pieces', 'products', 'services', 'payments'])
            ->get();

        // Aggregate value/paid/credit per calendar day. Paid is capped at the
        // order value so each stacked "income" bar equals the order value
        // (a rare overpayment doesn't inflate the paid segment).
        $byDate = [];
        foreach ($orders as $order) {
            $key = $order->created_at->toDateString();

            if (! isset($byDate[$key])) {
                $byDate[$key] = ['count' => 0, 'paid' => 0.0, 'credit' => 0.0];
            }

            $value = $order->calculateTotalPriceExcludingDraftPieces();
            $paid = min($order->calculatePaidAmount(), $value);

            $byDate[$key]['count']++;
            $byDate[$key]['paid'] += $paid;
            $byDate[$key]['credit'] += max(0, $value - $paid);
        }

        // Build a continuous day-by-day series so days with no orders show as
        // gaps rather than being skipped on the x-axis.
        $labels = [];
        $counts = [];
        $paid = [];
        $credit = [];

        $cursor = $from->copy();
        while ($cursor <= $to) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->format('d M');
            $counts[] = $byDate[$key]['count'] ?? 0;
            $paid[] = round($byDate[$key]['paid'] ?? 0.0, 2);
            $credit[] = round($byDate[$key]['credit'] ?? 0.0, 2);
            $cursor->addDay();
        }

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'labels' => $labels,
            'counts' => $counts,
            'paid' => $paid,
            'credit' => $credit,
            'totalOrders' => array_sum($counts),
            'totalPaid' => round(array_sum($paid), 2),
            'totalCredit' => round(array_sum($credit), 2),
            'totalIncome' => round(array_sum($paid) + array_sum($credit), 2),
        ]);
    }

    /**
     * Resolve the [from, to] day range for the daily stats chart from the
     * request, defaulting to the last 30 days and capping the span at 366 days.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDailyStatsRange(Request $request): array
    {
        $to = $this->parseDate($request->query('to')) ?? now();
        $from = $this->parseDate($request->query('from')) ?? $to->copy()->subDays(29);

        $from = $from->startOfDay();
        $to = $to->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366);
        }

        return [$from, $to];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return null;
        }
    }
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
