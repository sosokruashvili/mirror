<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
        
        // Get orders for display, excluding finished orders
        $orders = Order::with(['client', 'products', 'services', 'pieces'])
            ->where('status', '!=', 'finished')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Define status labels for display
        $statusLabels = [
            'draft' => 'Draft',
            'new' => 'New',
            'pending' => 'Pending',
            'working' => 'Working',
            'done' => 'Done',
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
}

