<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GcashPaymentProof;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    public function index(Request $request)
    {
        $query = Order::with(['customer', 'deliveryStaff', 'deliveryProof']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->get('search')) {
            $query->whereHas('customer', fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"))
                ->orWhere('order_code', 'like', "%{$search}%");
        }
        if ($request->get('proof_status') === 'pending') {
            $query->whereHas('deliveryProof', fn ($proof) => $proof->where('status', 'pending'));
        }

        $orders = $query->latest()->paginate(15)->withQueryString();
        $pendingDeliveryReviews = Order::whereHas('deliveryProof', fn ($proof) => $proof->where('status', 'pending'))->count();

        return view('admin.orders.index', compact('orders', 'pendingDeliveryReviews'));
    }

    public function create()
    {
        $products = Product::with('cylinder')->where('is_active', true)->get();
        return view('admin.orders.create', compact('products'));
    }

    public function store(Request $request)
    {
        // The Alpine <select> for cylinder ownership submits the literal
        // strings "true"/"false" (HTML attribute values are always strings),
        // but Laravel's `boolean` validation rule only accepts
        // true/false/1/0/"1"/"0" — not the words "true"/"false". Normalize
        // to real booleans here, before validation runs, so a valid
        // selection is never rejected.
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

        return redirect()->route('admin.orders.show', $order)
            ->with('success', "Order {$order->order_code} created successfully.");
    }

    public function show(Order $order)
    {
        $order->load(['items.product', 'items.cylinder', 'customer', 'deliveryStaff', 'deliveryProof', 'gcashPaymentProof.uploader', 'creator']);
        $deliveryStaff = User::where('role', 'delivery')->where('is_active', true)->get();
        return view('admin.orders.show', compact('order', 'deliveryStaff'));
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

    public function uploadGcashReceipt(Request $request, Order $order)
    {
        $validated = $request->validate([
            'payment_receipt' => 'required|image|max:4096',
        ]);

        if ($order->payment_method !== 'gcash' || $order->payment_status === 'paid_in_full'
            || ! in_array($order->status, ['pending', 'out_for_delivery'], true)) {
            return back()->with('error', 'A GCash receipt can only be recorded for an unpaid GCash order that is not completed or cancelled.');
        }

        $path = $validated['payment_receipt']->store('gcash-payment-proofs', 'public');
        $oldPath = null;
        $recorded = DB::transaction(function () use ($order, $request, $path, &$oldPath) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($lockedOrder->payment_method !== 'gcash' || $lockedOrder->payment_status === 'paid_in_full'
                || ! in_array($lockedOrder->status, ['pending', 'out_for_delivery'], true)) {
                return false;
            }

            $proof = GcashPaymentProof::query()->where('order_id', $lockedOrder->id)->lockForUpdate()->first();
            $oldPath = $proof?->image_path;
            $proofData = [
                'uploaded_by' => $request->user()->id,
                'image_path' => $path,
                'uploaded_at' => now(),
            ];

            if ($proof) {
                $proof->update($proofData);
            } else {
                $lockedOrder->gcashPaymentProof()->create($proofData);
            }

            $lockedOrder->update([
                'payment_status' => 'paid_in_full',
                'is_paid' => true,
                'paid_at' => now(),
                ...($lockedOrder->fulfillment_type === 'pickup' ? [
                    'status' => 'completed',
                    'delivered_at' => now(),
                ] : []),
            ]);

            return true;
        });

        if (! $recorded) {
            Storage::disk('public')->delete($path);

            return back()->with('error', 'The order changed before the GCash receipt could be recorded. Refresh the order and try again if it is still unpaid.');
        }

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with(
            'success',
            $order->fulfillment_type === 'pickup'
                ? 'GCash receipt uploaded. The pick-up order is marked Paid and completed.'
                : 'GCash receipt uploaded. The order is marked Paid and can now be assigned for delivery.'
        );
    }

    public function reviewDeliveryProof(Request $request, Order $order)
    {
        $validated = $request->validate([
            'decision' => 'required|in:approve,reject',
            'review_note' => 'nullable|string|max:500',
        ]);

        $reviewed = DB::transaction(function () use ($order, $validated, $request) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $proof = $order->deliveryProof()->lockForUpdate()->first();

            $paymentCanBeCompleted = $order->payment_method === 'cash'
                ? $order->payment_status === 'waiting_for_payment'
                : $order->payment_status === 'paid_in_full';

            if (! $proof || $proof->status !== 'pending' || $order->status !== 'out_for_delivery'
                || ! $paymentCanBeCompleted) {
                return false;
            }

            $reviewedAt = now();
            $approved = $validated['decision'] === 'approve';

            $proof->update([
                'status' => $approved ? 'approved' : 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => $reviewedAt,
                'review_note' => $validated['review_note'] ?? null,
                'completed_at' => $approved ? $reviewedAt : null,
            ]);

            if ($approved) {
                $updates = [
                    'status' => 'completed',
                    'delivered_at' => $reviewedAt,
                ];
                if ($order->payment_method === 'cash') {
                    $updates += [
                        'payment_status' => 'paid_in_full',
                        'is_paid' => true,
                        'paid_at' => $reviewedAt,
                    ];
                }
                $order->update($updates);
            }

            return true;
        });

        if (! $reviewed) {
            return back()->with('error', 'This order has no delivery photo awaiting review.');
        }

        return back()->with(
            'success',
            $validated['decision'] === 'approve'
                ? 'Delivery photo approved. The order is marked delivered.'
                : 'Delivery photo denied. The order remains out for delivery and the delivery staff can submit a new photo.'
        );
    }
}