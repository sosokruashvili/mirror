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

        $dateFrom = $pick('from', null);
        $dateTo = $pick('to', null);
        $productFilter = $normalizeArray($pick('product', []));
        $serviceFilter = $normalizeArray($pick('service', []));
        $stageFilter = $normalizeArray($pick('stage', []));
        $clientFilter = $pick('client', 'all');

        // Persist the just-applied filters for this user.
        if ($applied && ($user = backpack_user())) {
            $user->team_order_filters = [
                'from' => $dateFrom ?: null,
                'to' => $dateTo ?: null,
                'product' => $productFilter,
                'service' => $serviceFilter,
                'stage' => $stageFilter,
                'client' => $clientFilter,
            ];
            $user->save();
        }

        $products = \App\Models\Product::orderBy('title')->get();
        $clients = \App\Models\Client::orderBy('name')->get();
        $stages = \App\Models\Stage::orderBy('position')->orderBy('id')->get();

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
            'client',
            'products',
            'services',
            'pieces' => fn ($q) => $q->withCount('brokenGlasses')->orderBy('id'),
        ])
            ->whereNotIn('status', ['draft', 'finished'])
            ->orderBy('created_at', 'desc');

        if ($showArchived) {
            $ordersQuery->whereNotNull('archived_at');
        } else {
            $ordersQuery->whereNull('archived_at');
        }

        if (!empty($productFilter)) {
            $ordersQuery->whereHas('products', function ($q) use ($productFilter) {
                $q->whereIn('products.id', $productFilter);
            });
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
            // The '__none__' sentinel matches pieces that have no stage set yet.
            $wantsNoStage = in_array('__none__', $stageFilter, true);
            $realStages = array_values(array_filter($stageFilter, fn ($s) => $s !== '__none__'));

            $ordersQuery->whereHas('pieces', function ($q) use ($realStages, $wantsNoStage) {
                $q->where(function ($q) use ($realStages, $wantsNoStage) {
                    if (!empty($realStages)) {
                        $q->whereIn('stage', $realStages);
                    }

                    if ($wantsNoStage) {
                        $q->orWhereNull('stage')->orWhere('stage', '');
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

        $orders = $ordersQuery->get();

        return view('admin.team-orders', compact('orders', 'showArchived', 'products', 'productFilter', 'services', 'serviceFilter', 'stages', 'stageFilter', 'clients', 'clientFilter', 'dateFrom', 'dateTo'));
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
     * Update the production stage of a piece (AJAX) from the team orders page.
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

            $stage = $request->input('stage');
            $stage = ($stage === '' || $stage === null) ? null : $stage;

            if ($stage !== null && !array_key_exists($stage, piece_stages())) {
                return response()->json(['success' => false, 'message' => 'Invalid stage'], 422);
            }

            $piece->stage = $stage;
            $piece->save();

            return response()->json([
                'success' => true,
                'piece_id' => $piece->id,
                'stage' => $piece->stage,
                'stage_label' => piece_stage_ge($piece->stage),
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

