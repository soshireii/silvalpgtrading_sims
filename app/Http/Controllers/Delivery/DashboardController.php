<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $myOrders = Order::with('customer')
            ->where('assigned_delivery_id', $request->user()->id)
            ->where('status', 'out_for_delivery')
            ->orderBy('scheduled_delivery_date')
            ->orderBy('order_date')
            ->get();

        $completedToday = Order::where('assigned_delivery_id', $request->user()->id)
            ->where('status', 'completed')
            ->whereDate('delivered_at', today())
            ->count();

        return view('delivery.dashboard', compact('myOrders', 'completedToday'));
    }
}
