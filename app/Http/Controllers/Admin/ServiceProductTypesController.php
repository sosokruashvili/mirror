<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Services × product types matrix: which services the order form offers on a
 * mirror / glass / glass_pkg / lamix / service order (services.product_types,
 * read by public/assets/js/order-pieces-services.js). A service with nothing
 * ticked is offered on every type.
 *
 * Administrators only (super roles), on purpose: the matrix decides what every
 * employee can pick, so it is not part of the role-based access map.
 */
class ServiceProductTypesController extends Controller
{
    public function index(): View
    {
        $this->authorizeAdmin();

        $services = Service::query()
            ->with('stage:id,title,position')
            ->get()
            ->sortBy(fn (Service $s) => [$s->stage?->position ?? PHP_INT_MAX, $s->stage_id ?? PHP_INT_MAX, $s->id])
            ->values();

        return view('admin.service-product-types', [
            'title' => __('service.matrix.title'),
            'groups' => $services->groupBy(fn (Service $s) => $s->stage?->title ?? __('service.matrix.no_stage')),
            'types' => collect(Service::PRODUCT_TYPES)->mapWithKeys(fn (string $t) => [$t => product_type_ge($t)]),
            'usage' => $this->usageCounts(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $submitted = $request->input('types', []);
        $submitted = is_array($submitted) ? $submitted : [];
        $changed = 0;

        DB::transaction(function () use ($submitted, &$changed) {
            foreach (Service::query()->lockForUpdate()->get() as $service) {
                $before = $service->productTypes();
                $service->product_types = (array) ($submitted[$service->id] ?? []);
                if ($service->productTypes() !== $before) {
                    $service->save();
                    $changed++;
                }
            }
        });

        \Alert::success(__('service.matrix.saved', ['count' => $changed]))->flash();

        return redirect()->route('service-product-types.index');
    }

    /**
     * How many order lines used each service on each product type so far:
     * [service_id][product_type] => count. Shown next to each tick as a hint.
     *
     * @return array<int, array<string, int>>
     */
    private function usageCounts(): array
    {
        $counts = [];
        $rows = DB::table('order_service')
            ->join('orders', 'orders.id', '=', 'order_service.order_id')
            ->select('order_service.service_id', 'orders.product_type', DB::raw('count(*) as lines'))
            ->groupBy('order_service.service_id', 'orders.product_type')
            ->get();

        foreach ($rows as $row) {
            $counts[(int) $row->service_id][(string) $row->product_type] = (int) $row->lines;
        }

        return $counts;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(backpack_user()?->isSuperAdmin(), 403);
    }
}
