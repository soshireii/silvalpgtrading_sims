<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\DeliveryProof;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    /** Delivery staff only ever see orders assigned to them. */
    public function index(Request $request)
    {
        $orders = Order::with('customer', 'deliveryProof')
            ->where('assigned_delivery_id', $request->user()->id)
            ->orderBy('scheduled_delivery_date')
            ->latest('order_date')
            ->paginate(15);

        return view('delivery.orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->assigned_delivery_id === $request->user()->id, 403);
        $order->load('items.product', 'customer', 'deliveryProof');
        return view('delivery.orders.show', compact('order'));
    }

    /** Submit a delivery photo for admin review. Only approval completes the order. */
    public function complete(Request $request, Order $order)
    {
        abort_unless($order->assigned_delivery_id === $request->user()->id, 403);

        $paymentAllowsDeliveryProof = $order->payment_method === 'cash'
            ? $order->payment_status === 'waiting_for_payment'
            : $order->payment_status === 'paid_in_full';

        if ($order->status !== 'out_for_delivery' || ! $paymentAllowsDeliveryProof) {
            return back()->with('error', 'Only assigned orders with the required payment status can submit delivery proof.');
        }

        if (! $order->scheduled_delivery_date || $order->scheduled_delivery_date->isAfter(today())) {
            return back()->with('error', 'This delivery cannot be marked delivered before its scheduled delivery date.');
        }

        $existingProof = $order->deliveryProof;
        if ($existingProof?->status === 'pending') {
            return back()->with('error', 'This delivery photo is already waiting for admin review.');
        }

        $validated = $request->validate([
            'proof_image' => 'required|image|max:4096',
            'remarks' => 'nullable|string|max:500',
        ]);

        $path = $request->file('proof_image')->store('delivery-proofs', 'public');

        $proofData = [
            'delivery_staff_id' => $request->user()->id,
            'proof_image_path' => $path,
            'status' => 'pending',
            'remarks' => $validated['remarks'] ?? null,
            'review_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'completed_at' => null,
        ];

        if ($existingProof) {
            $oldPath = $existingProof->proof_image_path;
            $existingProof->update($proofData);
            Storage::disk('public')->delete($oldPath);
        } else {
            $order->deliveryProof()->create($proofData);
        }

        return redirect()->route('delivery.orders.show', $order)
            ->with('success', 'Delivery photo sent to the admin for review. The order will be marked delivered after approval.');
    }
}
