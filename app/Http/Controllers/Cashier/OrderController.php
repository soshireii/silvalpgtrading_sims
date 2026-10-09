<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    public function index(Request $request)
    {
        $query = Order::with(['customer', 'deliveryStaff']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->get('search')) {
            $query->whereHas('customer', fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"))
                ->orWhere('order_code', 'like', "%{$search}%");
        }

        $orders = $query->latest()->paginate(15)->withQueryString();

        return view('cashier.orders.index', compact('orders'));
    }

    public function create()
    {
        $products = Product::with('cylinder')->where('is_active', true)->get();
        return view('cashier.orders.create', compact('products'));
    }

    public function store(Request $request)
    {
        // The Alpine <select> for cylinder ownership submits the literal
        // strings "true"/"false" (HTML attribute values are always strings),
        // but Laravel's `boolean` validation rule only accepts
        // true/false/1/0/"1"/"0" — not the words "true"/"false". Normalize
        // to real booleans here, before validation runs, so a valid
        // selection is never rejected. (Same fix as Admin\OrderController.)
        $items = collect($request->input('items', []))
            ->map(function ($item) {
                $item['has_own_cylinder'] = filter_var(
                    $item['has_own_cylinder'] ?? true,
                    FILTER_VALIDATE_BOOLEAN
                );
                return $item;
            })
            ->all();

        $request->merge(['items' => $items]);

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:30',
            'fulfillment_type' => 'required|in:delivery,pickup',
            'delivery_address' => 'nullable|required_if:fulfillment_type,delivery|string|max:255',
            'scheduled_delivery_date' => 'nullable|required_if:fulfillment_type,delivery|date|after_or_equal:today',
            'payment_method' => 'required|in:cash,gcash',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.has_own_cylinder' => 'required|boolean',
        ]);

        $order = $this->orderService->createOrder(
            customerData: [
                'name' => $validated['customer_name'],
                'phone' => $validated['customer_phone'],
                'address' => $validated['delivery_address'] ?? 'Store pickup',
            ],
            items: $validated['items'],
            deliveryAddress: $validated['delivery_address'] ?? 'Store pickup',
            paymentMethod: $validated['payment_method'],
            createdBy: $request->user()->id,
            notes: $validated['notes'] ?? null,
            fulfillmentType: $validated['fulfillment_type'],
            scheduledDeliveryDate: $validated['scheduled_delivery_date'] ?? null,
        );

        return redirect()->route('cashier.orders.show', $order)
            ->with('success', "Order {$order->order_code} created successfully.");
    }

    public function show(Order $order)
    {
        $order->load(['items.product', 'items.cylinder', 'customer', 'deliveryStaff', 'deliveryProof', 'gcashPaymentProof', 'creator']);
        $deliveryStaff = User::where('role', 'delivery')->where('is_active', true)->get();
        return view('cashier.orders.show', compact('order', 'deliveryStaff'));
    }

    public function invoice(Order $order)
    {
        $order->load(['items.product', 'items.cylinder', 'customer']);

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
