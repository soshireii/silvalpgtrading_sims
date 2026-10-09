<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $todaySales = Order::whereDate('order_date', today())
            ->where('status', '!=', 'cancelled')
            ->sum('grand_total');

        $monthSales = Order::whereMonth('order_date', now()->month)
            ->whereYear('order_date', now()->year)
            ->where('status', '!=', 'cancelled')
            ->sum('grand_total');

        $monthExpenses = Expense::whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount');

        $pendingOrders = Order::whereIn('status', ['pending', 'out_for_delivery'])->count();
        $pendingDeliveryReviews = Order::whereHas('deliveryProof', fn ($query) => $query->where('status', 'pending'))->count();

        $products = Product::orderBy('current_stock')->get();
        $lowStockProducts = $products->filter(fn($p) => $p->stock_status !== 'green');

        $recentOrders = Order::with('customer')->latest()->take(8)->get();

        $salesTrend = Order::select(
            DB::raw('DATE(order_date) as date'),
            DB::raw('SUM(grand_total) as total')
        )
            ->where('status', '!=', 'cancelled')
            ->where('order_date', '>=', now()->subDays(6)->toDateString())
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('admin.dashboard', compact(
            'todaySales',
            'monthSales',
            'monthExpenses',
            'pendingOrders',
            'pendingDeliveryReviews',
            'products',
            'lowStockProducts',
            'recentOrders',
            'salesTrend'
        ));
    }
}
