<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrokenGlass;
use App\Models\Order;
use App\Models\Piece;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Prologue\Alerts\Facades\Alert;

class TeamOrderController extends Controller
{
    /**
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|null
     */
    private function rejectIfArchived(Order $order, Request $request)
    {
        if ($order->archived_at === null) {
            return null;
        }

        $message = 'Archived orders cannot be updated.';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        Alert::error($message)->flash();

        return redirect()->route('team.orders', ['view' => 'archived']);
    }

    /**
     * Display the team order processing page.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $view = $request->query('view');
        $showArchived = ($view === 'archived');

        // Reset button: clear the user's saved filters and reload unfiltered.
        if ($request->boolean('reset')) {
            if ($user = backpack_user()) {
                $user->team_order_filters = null;
                $user->save();
            }

            return redirect()->route('team.orders', $showArchived ? ['view' => 'archived'] : []);
        }

        // Filters come from the request when the filter form was submitted
        // ("applied=1"); otherwise fall back to the user's saved filters so the
        // page reopens with the last-used selection.
        [
            'applied' => $applied,
            'from' => $dateFrom,
            'to' => $dateTo,
            'product_type' => $productTypeFilter,
            'service' => $serviceFilter,
            'stage' => $stageFilter,
            'current_stage' => $currentStageFilter,
            'client' => $clientFilter,
        ] = $this->resolveFilters($request);

        // Persist the just-applied filters for this user.
        if ($applied && ($user = backpack_user())) {
            $user->team_order_filters = [
                'from' => $dateFrom ?: null,
                'to' => $dateTo ?: null,
                'product_type' => $productTypeFilter,
                'service' => $serviceFilter,
                'stage' => $stageFilter,
                'current_stage' => $currentStageFilter,
                'client' => $clientFilter,
            ];
            $user->save();
        }

        $clients = \App\Models\Client::orderBy('name')->get();
        $stages = \App\Models\Stage::orderBy('position')->orderBy('id')->get();

        // Distinct product types present on team orders, ordered by the canonical
        // list so the filter options read consistently.
        $productTypeOrder = ['mirror', 'glass', 'lamix', 'glass_pkg', 'service'];
        $productTypes = Order::whereNotIn('status', ['draft', 'finished'])
            ->whereNotNull('product_type')
            ->where('product_type', '!=', '')
            ->distinct()
            ->pluck('product_type')
            ->sortBy(fn ($type) => array_search($type, $productTypeOrder, true) === false
                ? PHP_INT_MAX
                : array_search($type, $productTypeOrder, true))
            ->values();

        $teamOrderScope = function ($q) use ($showArchived) {
            $q->whereNotIn('status', ['draft', 'finished']);
            if ($showArchived) {
                $q->whereNotNull('archived_at');
            } else {
                $q->whereNull('archived_at');
            }
        };

        $services = \App\Models\Service::whereHas('orders', $teamOrderScope)
            ->orderBy('title')
            ->get()
            ->unique(fn ($service) => $service->shortname ?: $service->title)
            ->sortBy(fn ($service) => $service->shortname ?: $service->title)
            ->values();

        $ordersQuery = Order::with([
            'authorUser',
            'client',
            'products',
            'services',
            'pieces' => fn ($q) => $q->withCount('brokenGlasses')->with('stages')->orderBy('id'),
        ])
            ->whereNotIn('status', ['draft', 'finished'])
            ->orderBy('created_at', 'desc');

        if ($showArchived) {
            $ordersQuery->whereNotNull('archived_at');
        } else {
            $ordersQuery->whereNull('archived_at');
        }

        $this->applyOrderFilters($ordersQuery, [
            'product_type' => $productTypeFilter,
            'service' => $serviceFilter,
            'stage' => $stageFilter,
            'current_stage' => $currentStageFilter,
            'client' => $clientFilter,
            'from' => $dateFrom,
            'to' => $dateTo,
        ]);

        // Paginate at 20 cards per page. withQueryString() keeps the active
        // filter query string on every pagination link so paging never drops
        // the applied filters.
        $orders = $ordersQuery->paginate(20)->withQueryString();

        return view('admin.team-orders', compact('orders', 'showArchived', 'productTypes', 'productTypeFilter', 'services', 'serviceFilter', 'stages', 'stageFilter', 'currentStageFilter', 'clients', 'clientFilter', 'dateFrom', 'dateTo'));
    }

    /**
     * Lightweight poll endpoint: return the IDs of the orders that currently
     * match the given filters (the same ones the page was rendered with). The
     * team page polls this periodically and reloads itself when a newly
     * confirmed order appears that is not yet shown. Returns only IDs so it
     * stays cheap to call every few seconds.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(Request $request)
    {
        $showArchived = ($request->query('view') === 'archived');

        $filters = $this->resolveFilters($request);

        $ordersQuery = Order::whereNotIn('status', ['draft', 'finished']);

        if ($showArchived) {
            $ordersQuery->whereNotNull('archived_at');
        } else {
            $ordersQuery->whereNull('archived_at');
        }

        $this->applyOrderFilters($ordersQuery, $filters);

        // Mirror the index page's ordering and 20-per-page pagination so the
        // poll compares against the SAME set of orders the current page shows.
        // Comparing against every matching order would report every card on the
        // other pages as "new" and reload the page endlessly.
        $page = $ordersQuery->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'ids' => $page->pluck('id'),
        ]);
    }

    /**
     * Resolve the active team-order filters from the request, falling back to
     * the user's saved filters when the filter form was not just submitted.
     *
     * @return array{applied:bool,from:?string,to:?string,product_type:array,service:array,stage:array,current_stage:array,client:mixed}
     */
    private function resolveFilters(Request $request): array
    {
        $applied = $request->has('applied');
        $saved = [];
        if (!$applied && ($user = backpack_user())) {
            $saved = is_array($user->team_order_filters) ? $user->team_order_filters : [];
        }

        $pick = function (string $key, $default) use ($request, $applied, $saved) {
            return $applied ? $request->query($key, $default) : ($saved[$key] ?? $default);
        };

        $normalizeArray = function ($value): array {
            if (!is_array($value)) {
                $value = ($value === 'all' || $value === null || $value === '') ? [] : [$value];
            }

            return array_values(array_filter($value, fn ($v) => $v !== '' && $v !== null));
        };

        return [
            'applied' => $applied,
            'from' => $pick('from', null),
            'to' => $pick('to', null),
            'product_type' => $normalizeArray($pick('product_type', [])),
            'service' => $normalizeArray($pick('service', [])),
            'stage' => $normalizeArray($pick('stage', [])),
            'current_stage' => $normalizeArray($pick('current_stage', [])),
            'client' => $pick('client', 'all'),
        ];
    }

    /**
     * Apply the service/stage/client/date filters to an order query.
     * The archived scope and base status scope are applied by the caller so the
     * same filtering can be shared between the page and the poll endpoint.
     */
    private function applyOrderFilters($ordersQuery, array $filters): void
    {
        $productTypeFilter = $filters['product_type'] ?? [];
        $serviceFilter = $filters['service'] ?? [];
        $stageFilter = $filters['stage'] ?? [];
        $currentStageFilter = $filters['current_stage'] ?? [];
        $clientFilter = $filters['client'] ?? 'all';
        $dateFrom = $filters['from'] ?? null;
        $dateTo = $filters['to'] ?? null;

        if (!empty($productTypeFilter)) {
            $ordersQuery->whereIn('product_type', $productTypeFilter);
        }

        if (!empty($serviceFilter)) {
            $ordersQuery->whereHas('services', function ($q) use ($serviceFilter) {
                $q->where(function ($q) use ($serviceFilter) {
                    foreach ($serviceFilter as $label) {
                        $q->orWhere('services.shortname', $label)
                            ->orWhere(function ($q) use ($label) {
                                $q->where(function ($q) {
                                    $q->whereNull('services.shortname')->orWhere('services.shortname', '');
                                })->where('services.title', $label);
                            });
                    }
                });
            });
        }

        if (!empty($stageFilter)) {
            // The '__none__' sentinel matches pieces with no services attached.
            $wantsNoStage = in_array('__none__', $stageFilter, true);
            $realStages = array_values(array_filter($stageFilter, fn ($s) => $s !== '__none__'));

            // Show orders containing a piece that HAS a selected stage but has
            // NOT completed it yet. A piece "has" a stage when it's the stage of
            // one of its services, or a universal stage (present on every piece).
            $universalSlugs = array_keys(piece_universal_stages());

            $ordersQuery->whereHas('pieces', function ($pieceQ) use ($realStages, $wantsNoStage, $universalSlugs) {
                $pieceQ->where(function ($pq) use ($realStages, $wantsNoStage, $universalSlugs) {
                    foreach ($realStages as $slug) {
                        $pq->orWhere(function ($p) use ($slug, $universalSlugs) {
                            // The piece must HAVE the stage. Universal stages are
                            // on every piece, so no extra condition is needed for
                            // them; otherwise it must have a service of that stage.
                            if (!in_array($slug, $universalSlugs, true)) {
                                $p->whereHas('services', function ($serviceQ) use ($slug) {
                                    $serviceQ->whereHas('stage', function ($stageQ) use ($slug) {
                                        $stageQ->where('stages.name', $slug);
                                    });
                                });
                            }

                            // ...and must NOT have completed it (no piece_stage record).
                            $p->whereDoesntHave('stages', function ($stageQ) use ($slug) {
                                $stageQ->where('stages.name', $slug);
                            });
                        });
                    }

                    if ($wantsNoStage) {
                        $pq->orWhereDoesntHave('services');
                    }
                });
            });
        }

        if (!empty($currentStageFilter)) {
            // Show orders containing a piece whose CURRENT stage (its highest
            // completed stage) is one of the selected stages. A piece's current
            // stage is $slug when it has completed $slug but has completed
            // nothing beyond it (no completed stage of a higher position).
            $stagePositions = \App\Models\Stage::pluck('position', 'name');

            $ordersQuery->whereHas('pieces', function ($pieceQ) use ($currentStageFilter, $stagePositions) {
                $pieceQ->where(function ($pq) use ($currentStageFilter, $stagePositions) {
                    foreach ($currentStageFilter as $slug) {
                        if (!isset($stagePositions[$slug])) {
                            continue;
                        }

                        $position = $stagePositions[$slug];

                        $pq->orWhere(function ($p) use ($slug, $position) {
                            $p->whereHas('stages', function ($stageQ) use ($slug) {
                                $stageQ->where('stages.name', $slug);
                            })->whereDoesntHave('stages', function ($stageQ) use ($position) {
                                $stageQ->where('stages.position', '>', $position);
                            });
                        });
                    }
                });
            });
        }

        if ($clientFilter !== 'all' && $clientFilter !== '' && $clientFilter !== null) {
            $ordersQuery->where('client_id', $clientFilter);
        }

        if (is_string($dateFrom) && $dateFrom !== '') {
            try {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $ordersQuery->where('created_at', '>=', $from);
            } catch (\Exception $e) {
                // ignore invalid date
            }
        }

        if (is_string($dateTo) && $dateTo !== '') {
            try {
                $to = Carbon::parse($dateTo)->endOfDay();
                $ordersQuery->where('created_at', '<=', $to);
            } catch (\Exception $e) {
                // ignore invalid date
            }
        }
    }

    /**
     * Archive an order (hide from team list).
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function archive(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);

            $order->archived_at = now();
            $order->save();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true]);
            }

            Alert::success('Order #' . $order->id . ' has been archived.')->flash();
            return redirect()->route('team.orders');
        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            Alert::error('Failed to archive order: ' . $e->getMessage())->flash();
            return redirect()->route('team.orders');
        }
    }

    public function unarchive(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);

            $order->archived_at = null;
            $order->save();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true]);
            }

            Alert::success('Order #' . $order->id . ' has been unarchived.')->flash();
            return redirect()->route('team.orders', ['view' => 'archived']);
        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            Alert::error('Failed to unarchive order: ' . $e->getMessage())->flash();
            return redirect()->route('team.orders', ['view' => 'archived']);
        }
    }

    /**
     * Finish an order by updating its status to 'finished'.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function finish(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);

            if ($response = $this->rejectIfArchived($order, $request)) {
                return $response;
            }
            
            // Update order status to finished
            $order->status = 'finished';
            $order->save();
            
            // Flash success message using Backpack's Alert system
            Alert::success('Order #' . $order->id . ' has been marked as finished.')->flash();
            
            return redirect()->route('team.orders');
        } catch (\Exception $e) {
            Alert::error('Failed to finish order: ' . $e->getMessage())->flash();
            return redirect()->route('team.orders');
        }
    }

    /**
     * Toggle the completion of a production stage for a piece (AJAX) from the
     * team orders page. Checking a stage records a dated completion in the
     * piece_stage pivot; unchecking removes it. An empty stage clears them all.
     *
     * @param  int  $id  Piece ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePieceStage(Request $request, $id)
    {
        try {
            $piece = Piece::findOrFail($id);

            if ($order = $piece->order) {
                if ($response = $this->rejectIfArchived($order, $request)) {
                    return $response;
                }
            }

            $stageSlug = $request->input('stage');
            $stageSlug = ($stageSlug === '' || $stageSlug === null) ? null : $stageSlug;

            if ($stageSlug === null) {
                // Clear all completed stages for this piece.
                $piece->setCompletedThroughStage(null);
            } else {
                $stage = \App\Models\Stage::where('name', $stageSlug)->first();

                if (!$stage) {
                    return response()->json(['success' => false, 'message' => 'Invalid stage'], 422);
                }

                $piece->setStageCompleted($stage, $request->boolean('completed'));
            }

            $currentStage = $piece->currentStageName();

            return response()->json([
                'success' => true,
                'piece_id' => $piece->id,
                'stage' => $currentStage,
                'stage_label' => piece_stage_ge($currentStage),
                'completed_stages' => $piece->completedStageNames(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Add a broken glass record for a piece (AJAX). Count is taken from broken_glasses table.
     *
     * @param  int  $id  Piece ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function markPieceBroken(Request $request, $id)
    {
        try {
            $piece = Piece::findOrFail($id);

            if ($order = $piece->order) {
                if ($response = $this->rejectIfArchived($order, $request)) {
                    return $response;
                }
            }

            BrokenGlass::create([
                'piece_id' => $piece->id,
                'description' => $request->input('description'),
            ]);

            $count = $piece->brokenGlasses()->count();

            // A broken sheet consumes an extra piece worth of material. Recalculate
            // the order's expenses (m²); draft pieces are excluded automatically.
            if ($order = $piece->order) {
                $order->expenses = $order->calculateExpenses();
                if (!in_array($order->status, ['draft', 'finished'], true)) {
                    $order->status = 'working';
                }
                $order->save();
            }

            return response()->json(['success' => true, 'broken' => $count]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

}

