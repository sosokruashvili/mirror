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
        $period = $this->resolvePeriod($request);
        [$from, $to] = $this->resolveDailyStatsRange($request, $period);
        $periodConfig = $this->periodConfig($period);

        $orders = Order::query()
            ->where('status', '!=', 'draft')
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->with(['pieces', 'products', 'services', 'payments'])
            ->get();

        // Aggregate value/paid/credit per bucket (day, month, or year). Paid is
        // capped at the order value so each stacked "income" bar equals the
        // order value (a rare overpayment doesn't inflate the paid segment).
        $byDate = [];
        foreach ($orders as $order) {
            $key = $order->created_at->format($periodConfig['keyFormat']);

            if (! isset($byDate[$key])) {
                $byDate[$key] = ['count' => 0, 'paid' => 0.0, 'credit' => 0.0];
            }

            $value = $order->calculateTotalPriceExcludingDraftPieces();
            $paid = min($order->calculatePaidAmount(), $value);

            $byDate[$key]['count']++;
            $byDate[$key]['paid'] += $paid;
            $byDate[$key]['credit'] += max(0, $value - $paid);
        }

        // Build a continuous bucket-by-bucket series so periods with no orders
        // show as gaps rather than being skipped on the x-axis.
        $labels = [];
        $counts = [];
        $paid = [];
        $credit = [];

        $cursor = $from->copy();
        while ($cursor <= $to) {
            $key = $cursor->format($periodConfig['keyFormat']);
            $labels[] = $cursor->format($periodConfig['labelFormat']);
            $counts[] = $byDate[$key]['count'] ?? 0;
            $paid[] = round($byDate[$key]['paid'] ?? 0.0, 2);
            $credit[] = round($byDate[$key]['credit'] ?? 0.0, 2);

            match ($periodConfig['step']) {
                'month' => $cursor->addMonth(),
                'year' => $cursor->addYear(),
                default => $cursor->addDay(),
            };
        }

        return response()->json([
            'period' => $period,
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
     * Validate the `period` query param, defaulting to daily grouping.
     */
    private function resolvePeriod(Request $request): string
    {
        $period = $request->query('period', 'days');

        return in_array($period, ['days', 'months', 'years'], true) ? $period : 'days';
    }

    /**
     * Bucket key/label/step configuration for a grouping period.
     *
     * @return array{keyFormat: string, labelFormat: string, step: string}
     */
    private function periodConfig(string $period): array
    {
        return match ($period) {
            'months' => ['keyFormat' => 'Y-m', 'labelFormat' => 'M Y', 'step' => 'month'],
            'years' => ['keyFormat' => 'Y', 'labelFormat' => 'Y', 'step' => 'year'],
            default => ['keyFormat' => 'Y-m-d', 'labelFormat' => 'D d M', 'step' => 'day'],
        };
    }

    /**
     * Order summary grouped by order product type for the product-type bar
     * chart.
     *
     * Returns, for each of the five product types (mirror / glass / lamix /
     * glass_pkg / service), the number of confirmed orders of that type and
     * the total value of those orders. Draft orders (and draft pieces within
     * an order's value) are excluded.
     *
     * The date range defaults to the last 30 days and can be overridden with
     * `from` / `to` query params (YYYY-MM-DD), matching the daily stats chart.
     */
    public function getProductTypeStatsChart(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveDailyStatsRange($request);

        // Fixed set/order of product types so every bucket always appears on the
        // chart, even with zero orders in the selected range.
        $types = ['mirror', 'glass', 'service'];

        $byType = [];
        foreach ($types as $type) {
            $byType[$type] = ['count' => 0, 'value' => 0.0];
        }

        $orders = Order::query()
            ->where('status', '!=', 'draft')
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->with(['pieces', 'products', 'services'])
            ->get();

        foreach ($orders as $order) {
            $type = strtolower((string) $order->product_type);
            if (! isset($byType[$type])) {
                // Ignore any unexpected/legacy product type value.
                continue;
            }

            $byType[$type]['count']++;
            $byType[$type]['value'] += $order->calculateTotalPriceExcludingDraftPieces();
        }

        $labels = [];
        $counts = [];
        $values = [];
        foreach ($types as $type) {
            $labels[] = product_type_ge($type);
            $counts[] = $byType[$type]['count'];
            $values[] = round($byType[$type]['value'], 2);
        }

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'labels' => $labels,
            'counts' => $counts,
            'values' => $values,
            'totalOrders' => array_sum($counts),
            'totalValue' => round(array_sum($values), 2),
        ]);
    }

    /**
     * Resolve the [from, to] range for the stats charts from the request.
     *
     * When `from`/`to` query params are absent the range defaults to a sensible
     * window for the grouping period (last 30 days / 12 months / 10 years). The
     * bounds are snapped to the period's boundaries so month/year buckets are
     * whole, and the span is capped to keep the in-memory aggregation bounded.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDailyStatsRange(Request $request, string $period = 'days'): array
    {
        $to = $this->parseDate($request->query('to')) ?? now();
        $from = $this->parseDate($request->query('from')) ?? match ($period) {
            'months' => $to->copy()->subMonths(11),
            'years' => $to->copy()->subYears(9),
            default => $to->copy()->subDays(29),
        };

        // Snap the bounds to the period's boundaries so partial months/years
        // aren't split across buckets.
        [$from, $to] = match ($period) {
            'months' => [$from->startOfMonth(), $to->endOfMonth()],
            'years' => [$from->startOfYear(), $to->endOfYear()],
            default => [$from->startOfDay(), $to->startOfDay()],
        };

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        // Cap the span (in days) so the eager-loaded aggregation stays bounded.
        $maxDays = match ($period) {
            'months' => 366 * 11,
            'years' => 366 * 30,
            default => 366,
        };

        if ($from->diffInDays($to) > $maxDays) {
            $from = $to->copy()->subDays($maxDays);
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
     * Top 10 order authors by order count for the selected preset range.
     *
     * Returns each author's order count and total order value (second series).
     * Draft orders are excluded; values use calculateTotalPriceExcludingDraftPieces().
     *
     * Range presets via `range` query param: this_week | this_month | last_month | last_year.
     */
    public function getTopUsersChart(Request $request): JsonResponse
    {
        $range = $this->resolveTopUsersRangeKey($request);
        [$from, $to] = $this->resolveTopUsersRange($range);

        $orders = Order::query()
            ->where('status', '!=', 'draft')
            ->whereNotNull('author')
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->with(['pieces', 'products', 'services', 'authorUser'])
            ->get();

        $byUser = [];
        foreach ($orders as $order) {
            $userId = (int) $order->author;
            if (! isset($byUser[$userId])) {
                $byUser[$userId] = [
                    'name' => $order->authorUser?->name ?? ('User #'.$userId),
                    'count' => 0,
                    'value' => 0.0,
                ];
            }

            $byUser[$userId]['count']++;
            $byUser[$userId]['value'] += $order->calculateTotalPriceExcludingDraftPieces();
        }

        uasort($byUser, function (array $a, array $b): int {
            return $b['count'] <=> $a['count'] ?: $b['value'] <=> $a['value'];
        });

        $top = array_slice($byUser, 0, 10, true);

        $labels = [];
        $counts = [];
        $values = [];
        foreach ($top as $row) {
            $labels[] = $row['name'];
            $counts[] = $row['count'];
            $values[] = round($row['value'], 2);
        }

        return response()->json([
            'range' => $range,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'labels' => $labels,
            'counts' => $counts,
            'values' => $values,
            'totalOrders' => array_sum($counts),
            'totalValue' => round(array_sum($values), 2),
        ]);
    }

    /**
     * Per-user production stage-completion stats for the User Stats page.
     *
     * Ranks users by how many piece stages they marked complete in the range,
     * broken down per stage (a stacked bar). Data comes from the piece_stage
     * pivot's `user_id` / `completed_at` — the authoritative record of who
     * finished what.
     *
     * Excluded so the numbers reflect real per-worker effort:
     *  - rows with no `user_id` (historical backfill, no known actor);
     *  - pieces of draft orders (not in production);
     *  - the auto-closed final 'completion' stage — no worker is responsible for
     *    it (it auto-completes once the real stages are done), so counting it
     *    would double-credit whoever finished the last gating stage.
     *
     * Reuses the same range presets as the top-users chart.
     */
    public function getStageCompletionsChart(Request $request): JsonResponse
    {
        $range = $this->resolveTopUsersRangeKey($request);
        [$from, $to] = $this->resolveTopUsersRange($range);

        $rows = DB::table('piece_stage as ps')
            ->join('stages as s', 's.id', '=', 'ps.stage_id')
            ->join('pieces as p', 'p.id', '=', 'ps.piece_id')
            ->join('orders as o', 'o.id', '=', 'p.order_id')
            ->join('users as u', 'u.id', '=', 'ps.user_id')
            ->whereNotNull('ps.user_id')
            ->where('o.status', '!=', 'draft')
            ->where('s.name', '!=', 'completion')
            ->whereBetween('ps.completed_at', [$from, $to->copy()->endOfDay()])
            ->groupBy('ps.user_id', 'u.name', 's.id', 's.title', 's.color', 's.position')
            ->select(
                'ps.user_id',
                'u.name as user_name',
                's.id as stage_id',
                's.title as stage_title',
                's.color as stage_color',
                's.position as stage_position',
                DB::raw('COUNT(*) as completions')
            )
            ->get();

        // Pivot the flat rows into per-user totals and per-stage breakdowns.
        $stages = [];   // stage_id => ['title','color','position']
        $byUser = [];   // user_id  => ['name','total','counts'=>[stage_id=>n]]

        foreach ($rows as $row) {
            $stages[$row->stage_id] ??= [
                'title' => $row->stage_title,
                'color' => $row->stage_color,
                'position' => (int) $row->stage_position,
            ];

            if (! isset($byUser[$row->user_id])) {
                $byUser[$row->user_id] = [
                    'name' => $row->user_name ?? ('User #'.$row->user_id),
                    'total' => 0,
                    'counts' => [],
                ];
            }

            $count = (int) $row->completions;
            $byUser[$row->user_id]['counts'][$row->stage_id] = $count;
            $byUser[$row->user_id]['total'] += $count;
        }

        // Stages ordered by production position so the stacked segments read
        // left-to-right in the real flow.
        uasort($stages, fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        // Top 10 users by total completions.
        uasort($byUser, fn (array $a, array $b): int => $b['total'] <=> $a['total']);
        $top = array_slice($byUser, 0, 10, true);

        $labels = array_map(fn (array $u): string => $u['name'], array_values($top));

        // One dataset per stage (a stacked-bar series), in production order.
        $datasets = [];
        foreach ($stages as $stageId => $stage) {
            $data = [];
            foreach ($top as $user) {
                $data[] = $user['counts'][$stageId] ?? 0;
            }

            $datasets[] = [
                'label' => $stage['title'],
                'color' => $stage['color'],
                'data' => $data,
            ];
        }

        return response()->json([
            'range' => $range,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'labels' => $labels,
            'datasets' => $datasets,
            'totalCompletions' => array_sum(array_map(fn (array $u): int => $u['total'], $top)),
            'userCount' => count($byUser),
        ]);
    }

    /**
     * Validate the top-users `range` query param.
     */
    private function resolveTopUsersRangeKey(Request $request): string
    {
        $range = $request->query('range', 'this_month');

        return in_array($range, ['this_week', 'this_month', 'last_month', 'last_year'], true)
            ? $range
            : 'this_month';
    }

    /**
     * Resolve [from, to] for a top-users preset range.
     *
     * Weeks start on Monday. "Last year" is the previous calendar year.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveTopUsersRange(string $range): array
    {
        $now = now();

        return match ($range) {
            'this_week' => [
                $now->copy()->startOfWeek(Carbon::MONDAY),
                $now->copy()->endOfDay(),
            ],
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'last_year' => [
                $now->copy()->subYear()->startOfYear(),
                $now->copy()->subYear()->endOfYear(),
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfDay(),
            ],
        };
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
