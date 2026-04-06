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
     * Display the team order processing page.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $view = $request->query('view');
        $showArchived = ($view === 'archived');
        $dateFrom = $request->query('from');
        $dateTo = $request->query('to');
        $status = $request->query('status', 'all');

        // Get all orders for status counts (including finished)
        $allOrders = Order::all();
        
        // Calculate status counts from all orders
        $statusCounts = $allOrders->groupBy('status')->map->count();
        
        // Get orders for display, excluding draft, ready, and finished orders
        $ordersQuery = Order::with(['client', 'products', 'services', 'pieces'])
            ->whereNotIn('status', ['draft', 'ready', 'finished'])
            ->orderBy('created_at', 'desc');

        if ($showArchived) {
            $ordersQuery->whereNotNull('archived_at');
        } else {
            $ordersQuery->whereNull('archived_at');
        }

        if (is_string($status) && $status !== '' && $status !== 'all') {
            $ordersQuery->where('status', $status);
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
        
        // Define status labels for display
        $statusLabels = [
            'draft' => 'Draft',
            'new' => 'New',
            'pending' => 'Pending',
            'working' => 'Working',
            'done' => 'Done',
            'ready' => 'Ready',
            'finished' => 'Finished',
        ];
        
        // Format status counts - only include statuses that exist in the database
        $statusCountsFormatted = [];
        foreach ($statusCounts as $statusKey => $count) {
            $statusCountsFormatted[$statusKey] = [
                'label' => $statusLabels[$statusKey] ?? ucfirst($statusKey),
                'count' => $count
            ];
        }

        return view('admin.team-orders', compact('orders', 'statusCountsFormatted', 'showArchived', 'statusLabels', 'dateFrom', 'dateTo', 'status'));
    }

    /**
     * Archive an order (hide from team list).
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function archive($id)
    {
        try {
            $order = Order::findOrFail($id);

            $order->archived_at = now();
            $order->save();

            Alert::success('Order #' . $order->id . ' has been archived.')->flash();
            return redirect()->route('team.orders');
        } catch (\Exception $e) {
            Alert::error('Failed to archive order: ' . $e->getMessage())->flash();
            return redirect()->route('team.orders');
        }
    }

    public function unarchive($id)
    {
        try {
            $order = Order::findOrFail($id);

            $order->archived_at = null;
            $order->save();

            Alert::success('Order #' . $order->id . ' has been unarchived.')->flash();
            return redirect()->route('team.orders', ['view' => 'archived']);
        } catch (\Exception $e) {
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
    public function finish($id)
    {
        try {
            $order = Order::findOrFail($id);
            
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
     * Mark a piece as ready by updating its status to 'ready'.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function markPieceReady($id)
    {
        try {
            $piece = Piece::findOrFail($id);
            $order = $piece->order;
            
            // Update piece status to ready
            $piece->status = 'ready';
            $piece->save();
            
            // Check if all pieces of the order are ready and update order status
            $order->updateStatusIfAllPiecesReady();
            if(!$order->allPiecesReady()) {
                $order->status = 'working';
                $order->save();
            }
            
            Alert::success('Piece #' . $piece->id . ' has been marked as ready.')->flash();
            
            return redirect()->route('order.show', $order->id);
        } catch (\Exception $e) {
            Alert::error('Failed to mark piece as ready: ' . $e->getMessage())->flash();
            return redirect()->back();
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
            BrokenGlass::create([
                'piece_id' => $piece->id,
                'description' => $request->input('description'),
            ]);
            $count = $piece->brokenGlasses()->count();

            return response()->json(['success' => true, 'broken' => $count]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

}

