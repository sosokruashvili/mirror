<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service statistics page: per-service completed quantity and total money.
 *
 * A service line (order_service pivot row) counts as "completed" once the piece
 * it was applied to has finished the production stage that owns the service
 * (a piece_stage row for that piece + services.stage_id). Quantity is summed
 * from the service's billable measure column — perimeter for edges/frames, area
 * for matting, length_cm for cutouts, quantity for per-piece work, and so on —
 * resolved exactly the way the invoice does (see order_service_measure() in
 * app/helpers.php). Money is the sum of the per-line price_gel already stored on
 * the pivot. Draft orders are excluded. An optional completed-date range narrows
 * the report.
 *
 * Access is admin-only: the service-stats.view permission exists (config/access
 * .php) but is granted to no role, so only administrators reach it via the
 * Gate::before() bypass.
 */
class ServiceStatsController extends Controller
{
    /**
     * Pivot measure column => display unit, in resolution-preference order.
     * Mirrors the $measures list in order_service_measure().
     */
    private const MEASURES = [
        'area' => 'კვ.მ',
        'perimeter' => 'მ',
        'length_cm' => 'სმ',
        'distance' => 'კმ',
        'tape_length' => 'მ',
        'foam_length' => 'მ',
        'sensor_quantity1' => 'ცალი',
        'quantity' => 'ცალი',
    ];

    /**
     * Render the service stats table.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        [$from, $to] = $this->dateRange();

        $totals = $this->completedTotals($from, $to);

        $rows = [];
        $grandMoney = 0.0;

        $services = Service::with('stage')
            ->orderBy('stage_id')
            ->orderBy('id')
            ->get();

        foreach ($services as $service) {
            [$field, $unit] = $this->measureFor($service);

            $agg = $totals->get($service->id);
            $money = (float) ($agg->money ?? 0);

            // Flat-price services (no measure column enabled, e.g. მონტაჟი,
            // ტრანსპორტირება) are counted per completed line.
            $quantity = $agg
                ? ($field ? (float) ($agg->{'sum_' . $field} ?? 0) : (int) $agg->cnt)
                : 0.0;

            $rows[] = [
                'id' => $service->id,
                'title' => $service->title,
                'stage' => $service->stage->title ?? '—',
                'unit' => $unit,
                'quantity' => $quantity,
                'money' => $money,
                'active' => $agg !== null,
            ];

            $grandMoney += $money;
        }

        return view('admin.service-stats', [
            'title' => 'Service Stats',
            'rows' => $rows,
            'grandMoney' => $grandMoney,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Read and normalise the from/to date filters (Y-m-d) from the request.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function dateRange(): array
    {
        $parse = function (?string $value): ?string {
            if (! $value) {
                return null;
            }

            try {
                return Carbon::parse($value)->toDateString();
            } catch (\Throwable $e) {
                return null;
            }
        };

        return [$parse(request('from')), $parse(request('to'))];
    }

    /**
     * Aggregate completed order_service lines per service.
     *
     * The piece_stage join is what makes a line "completed": it matches only when
     * the piece has a completion record for the service's own stage. The date
     * range, when given, is applied to that completion date.
     *
     * @param  string|null  $from
     * @param  string|null  $to
     * @return \Illuminate\Support\Collection
     */
    private function completedTotals(?string $from, ?string $to)
    {
        $select = [
            'os.service_id',
            DB::raw('COUNT(*) as cnt'),
            DB::raw('SUM(os.price_gel) as money'),
        ];

        foreach (array_keys(self::MEASURES) as $col) {
            $select[] = DB::raw("SUM(os.{$col}) as sum_{$col}");
        }

        return DB::table('order_service as os')
            ->join('services as s', 'os.service_id', '=', 's.id')
            ->join('orders as o', 'os.order_id', '=', 'o.id')
            ->join('piece_stage as ps', function ($join) {
                $join->on('ps.piece_id', '=', 'os.piece_id')
                    ->on('ps.stage_id', '=', 's.stage_id');
            })
            ->where('o.status', '!=', 'draft')
            ->when($from, fn ($q) => $q->whereDate('ps.completed_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('ps.completed_at', '<=', $to))
            ->groupBy('os.service_id')
            ->select($select)
            ->get()
            ->keyBy('service_id');
    }

    /**
     * Resolve a service's billable measure column and display unit from its
     * extra_field_names, using the same preference order as the invoice. Services
     * with no measure column enabled return [null, 'ცალი'] and are counted.
     *
     * @param  \App\Models\Service  $service
     * @return array{0: ?string, 1: string}
     */
    private function measureFor(Service $service): array
    {
        $enabled = is_array($service->extra_field_names) ? $service->extra_field_names : [];

        foreach (self::MEASURES as $field => $unit) {
            if (in_array($field, $enabled, true)) {
                return [$field, $unit];
            }
        }

        return [null, 'ცალი'];
    }
}
