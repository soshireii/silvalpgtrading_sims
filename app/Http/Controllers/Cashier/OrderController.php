<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Concerns\ManagesOrders;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ManagesOrders;

    public function __construct(protected OrderService $orderService) {}

    public function index(Request $request)
    {
        $query = Order::with(['customer', 'deliveryStaff']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->get('search')) {
            $query->search($search);
        }

        $orders = $query->latest()->paginate(15)->withQueryString();
        $products = Product::with('cylinder')->where('is_active', true)->orderBy('name')->get();

        return view('cashier.orders.index', compact('orders', 'products'));
    }

    /** "Take New Order" is a pop-up on the orders page now; old links still land there. */
    public function create()
    {
        return redirect()->route('cashier.orders.index', ['new' => 1]);
    }

    public function store(Request $request)
    {
        $order = $this->createOrderFromRequest($request, $this->orderService);

        return redirect()->route('cashier.orders.show', $order)
            ->with('success', "Order {$order->order_code} created successfully.");
    }

    public function show(Order $order)
    {
        $order->load(['items.product.cylinder', 'customer', 'deliveryStaff', 'deliveryProof', 'gcashPaymentProof', 'creator']);
        $deliveryStaff = User::deliveryStaff()->active()->orderByName()->get();
        return view('cashier.orders.show', compact('order', 'deliveryStaff'));
    }

    public function invoice(Order $order)
    {
        $order->load(['items.product.cylinder', 'customer']);

        return view('orders.invoice', compact('order'));
    }

    public function assign(Request $request, Order $order)
    {
        $validated = $request->validate(['delivery_staff_id' => 'required|exists:users,id']);
        $this->orderService->assignDelivery($order, $validated['delivery_staff_id']);
        return back()->with('success', 'Order assigned to delivery staff.');
    }

    public function cancel(Request $request, Order $order)
    {
        $validated = $request->validate(['reason' => 'nullable|string|max:255']);
        $this->orderService->cancelOrder($order, $request->user()->id, $validated['reason'] ?? null);
        return back()->with('success', 'Order cancelled and stock restored.');
    }

    public function markPickupAsPaid(Order $order)
    {
        $this->orderService->markPickupAsPaid($order);

        return back()->with('success', "Pick-up order {$order->order_code} marked paid and completed.");
    }
}
