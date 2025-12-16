<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Piece;
use Illuminate\Http\Request;
use Prologue\Alerts\Facades\Alert;

class TeamOrderController extends Controller
{
    /**
     * Display the team order processing page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get all orders for status counts (including finished)
        $allOrders = Order::all();
        
        // Calculate status counts from all orders
        $statusCounts = $allOrders->groupBy('status')->map->count();
        
        // Get orders for display, excluding draft, ready, and finished orders
        $orders = Order::with(['client', 'products', 'services', 'pieces'])
            ->whereNotIn('status', ['draft', 'ready', 'finished'])
            ->orderBy('created_at', 'desc')
            ->get();
        
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
        foreach ($statusCounts as $status => $count) {
            $statusCountsFormatted[$status] = [
                'label' => $statusLabels[$status] ?? ucfirst($status),
                'count' => $count
            ];
        }

        return view('admin.team-orders', compact('orders', 'statusCountsFormatted'));
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
}

