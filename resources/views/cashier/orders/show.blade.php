<x-app-layout title="Order {{ $order->order_code }}" header="Order {{ $order->order_code }}">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 space-y-6">

            @if($order->payment_method === 'gcash' && $order->gcashPaymentProof)
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold text-gray-900">GCash Payment Receipt</h2>
                    <x-badge color="green" label="Paid" />
                </div>
                <img src="{{ asset('storage/' . $order->gcashPaymentProof->image_path) }}" alt="GCash payment receipt" class="rounded-lg border border-gray-200 max-h-80 object-contain">
                <p class="text-xs text-gray-400 mt-2">Receipt recorded on {{ $order->gcashPaymentProof->uploaded_at->format('M j, Y g:i A') }}</p>
            </div>
            @endif

            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-900">Items</h2>
                    <x-badge :color="$order->is_delayed ? 'yellow' : $order->status_color" :label="$order->is_delayed ? 'Delayed' : $order->status_label" />
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100">
                            <th class="py-2 pr-3 font-medium">Product</th>
                            <th class="py-2 pr-3 font-medium">Qty</th>
                            <th class="py-2 pr-3 font-medium">Cylinder</th>
                            <th class="py-2 pr-3 font-medium">Gas price</th>
                            <th class="py-2 pr-3 font-medium">Cylinder price</th>
                            <th class="py-2 font-medium text-right">Line total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        <tr class="border-b border-gray-50">
                            <td class="py-2.5 pr-3 text-gray-800">{{ $item->product->name ?? 'Deleted product' }} @if($item->cylinder) <span class="text-gray-400">({{ $item->cylinder->name }})</span> @endif</td>
                            <td class="py-2.5 pr-3 text-gray-600">{{ $item->quantity }}</td>
                            <td class="py-2.5 pr-3 text-gray-600">{{ $item->has_own_cylinder ? 'Own (exchange)' : 'Cylinder purchased' }}</td>
                            <td class="py-2.5 pr-3 text-gray-600">₱{{ number_format($item->unit_gas_price, 2) }}</td>
                            <td class="py-2.5 pr-3 text-gray-600">₱{{ number_format($item->unit_cylinder_fee, 2) }}</td>
                            <td class="py-2.5 text-right text-gray-800 font-medium">₱{{ number_format($item->line_total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-4 pt-4 border-t border-gray-100 space-y-1 text-sm">
                    <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>₱{{ number_format($order->subtotal, 2) }}</span></div>
                    <div class="flex justify-between text-gray-600"><span>Cylinder purchases</span><span>₱{{ number_format($order->cylinder_fee_total, 2) }}</span></div>
                    <div class="flex justify-between text-base font-semibold text-gray-900"><span>Grand total</span><span>₱{{ number_format($order->grand_total, 2) }}</span></div>
                </div>
            </div>

            @if($order->deliveryProof)
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold text-gray-900">Delivery Photo</h2>
                    <x-badge :color="$order->deliveryProof->status === 'approved' ? 'green' : (in_array($order->deliveryProof->status, ['rejected', 'cancelled']) ? 'red' : 'yellow')" :label="str($order->deliveryProof->status)->headline()" />
                </div>
                <img src="{{ asset('storage/' . $order->deliveryProof->proof_image_path) }}" alt="Delivery proof" class="rounded-lg border border-gray-200 max-h-80 object-cover">
                @if($order->deliveryProof->remarks)
                    <p class="text-sm text-gray-600 mt-3">Staff note: {{ $order->deliveryProof->remarks }}</p>
                @endif
                @if($order->deliveryProof->review_note)
                    <p class="text-sm text-gray-600 mt-2">Review note: {{ $order->deliveryProof->review_note }}</p>
                @endif
                <p class="text-xs text-gray-400 mt-2">Submitted {{ $order->deliveryProof->created_at->format('M j, Y g:i A') }}</p>
            </div>
            @endif
        </div>

        <div class="space-y-6">

            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold text-gray-900">Customer</h2>
                    <a href="{{ route('cashier.orders.invoice', $order) }}" target="_blank" class="text-sm font-medium text-maroon-600 hover:underline">View / Print Invoice</a>
                </div>
                <p class="text-sm text-gray-800">{{ $order->customer->name }}</p>
                <p class="text-sm text-gray-500">{{ $order->customer->phone }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ $order->fulfillment_type === 'pickup' ? 'Pick-up at store' : $order->delivery_address }}</p>
                <div class="mt-3 pt-3 border-t border-gray-100 text-sm text-gray-500">
                    Payment: <span class="capitalize font-medium text-gray-700">{{ $order->payment_method }}</span>
                    — <x-badge :color="$order->payment_status === 'paid_in_full' ? 'green' : 'yellow'" :label="$order->payment_status_label" />
                </div>
                <div class="text-xs text-gray-400 mt-1">Order date: {{ $order->order_date->format('M j, Y') }}</div>
                <div class="text-xs text-gray-400 mt-1">Ordered at: {{ $order->ordered_at?->format('M j, Y g:i A') }}</div>
                <div class="text-xs text-gray-500 mt-2">Invoice: {{ $order->invoice_number }}</div>
                <div class="text-xs text-gray-500 mt-1">Fulfillment: {{ $order->fulfillment_type === 'pickup' ? 'Pick-up' : 'Delivery' }}</div>
                @if($order->fulfillment_type === 'delivery')
                    <div class="text-xs text-gray-500 mt-1">Scheduled delivery: {{ $order->scheduled_delivery_date?->format('M j, Y') ?? 'Not scheduled' }}</div>
                @endif
                @if($order->fulfillment_type === 'pickup' && $order->status === 'pending' && $order->payment_status !== 'paid_in_full')
                    @if($order->payment_method === 'cash')
                        <form method="POST" action="{{ route('cashier.orders.pickup-paid', $order) }}" class="mt-4"
                              onsubmit="return confirm('Confirm that payment was received and mark this pick-up order as paid and completed?');">
                            @csrf
                            <button type="submit" class="w-full bg-status-success text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90">Mark as Paid</button>
                        </form>
                    @else
                        <p class="mt-3 text-xs text-status-warning">The owner must upload the GCash receipt to mark this pick-up order paid and complete it.</p>
                    @endif
                @elseif($order->fulfillment_type === 'pickup' && $order->status === 'completed')
                    <p class="mt-3 text-sm font-medium text-status-success">Pick-up completed and paid.</p>
                @endif
                @if($order->notes)
                    <p class="text-xs text-gray-500 mt-2 italic">{{ $order->notes }}</p>
                @endif
            </div>

            @if($order->status === 'pending' && $order->fulfillment_type === 'delivery')
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <h2 class="font-semibold text-gray-900 mb-3">Assign Delivery</h2>
                @if($order->payment_method === 'gcash' && $order->payment_status !== 'paid_in_full')
                    <p class="text-sm text-status-warning">The owner must upload and confirm the GCash receipt before this order can be assigned for delivery.</p>
                @else
                <form method="POST" action="{{ route('cashier.orders.assign', $order) }}" class="space-y-3">
                    @csrf
                    <select name="delivery_staff_id" required class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                        <option value="">Select delivery staff…</option>
                        @foreach($deliveryStaff as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="w-full bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg">
                        Assign &amp; Mark Out for Delivery
                    </button>
                </form>
                @endif
            </div>
            @endif

            @if(!in_array($order->status, ['completed', 'cancelled']))
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <h2 class="font-semibold text-gray-900 mb-3">Cancel Order</h2>
                <form method="POST" action="{{ route('cashier.orders.cancel', $order) }}"
                      onsubmit="return confirm('Cancel this order and restock the items?');" class="space-y-3">
                    @csrf
                    <input type="text" name="reason" placeholder="Reason (optional)" class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    <button type="submit" class="w-full bg-status-danger-bg hover:bg-red-200 text-status-danger text-sm font-medium px-4 py-2.5 rounded-lg">
                        Cancel Order
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>

</x-app-layout>
