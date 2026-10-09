<x-app-layout title="Order {{ $order->order_code }}" header="Order {{ $order->order_code }}">

    @php
        $deliveryDateDue = $order->scheduled_delivery_date && $order->scheduled_delivery_date->lte(today());
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-900">Items to deliver</h2>
                    <x-badge :color="$order->is_delayed ? 'yellow' : $order->status_color" :label="$order->is_delayed ? 'Delayed' : $order->status_label" />
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100">
                            <th class="py-2 pr-3 font-medium">Product</th>
                            <th class="py-2 pr-3 font-medium">Qty</th>
                            <th class="py-2 font-medium text-right">Line total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        <tr class="border-b border-gray-50">
                            <td class="py-2.5 pr-3 text-gray-800">{{ $item->product->name ?? 'Deleted product' }}</td>
                            <td class="py-2.5 pr-3 text-gray-600">{{ $item->quantity }}</td>
                            <td class="py-2.5 text-right text-gray-800 font-medium">₱{{ number_format($item->line_total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between text-base font-semibold text-gray-900">
                    <span>Invoice {{ $order->invoice_number }} — {{ strtoupper($order->payment_method) }} / {{ $order->payment_status_label }}</span>
                    <span>₱{{ number_format($order->grand_total, 2) }}</span>
                </div>
            </div>

            @if($order->status === 'out_for_delivery' && $order->deliveryProof?->status === 'pending')
            <div class="bg-status-warning-bg border border-yellow-200 rounded-xl p-5">
                <h2 class="font-semibold text-status-warning mb-2">Photo sent for admin review</h2>
                <img src="{{ asset('storage/' . $order->deliveryProof->proof_image_path) }}" alt="Submitted delivery proof" class="rounded-lg border border-yellow-200 max-h-80 object-cover">
                <p class="text-sm text-status-warning mt-3">The order will be marked delivered after the admin approves this photo{{ $order->payment_method === 'cash' ? ', and cash payment will be marked paid in full' : '' }}.</p>
            </div>
            @elseif($order->status === 'out_for_delivery')
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <h2 class="font-semibold text-gray-900 mb-3">{{ $order->deliveryProof?->status === 'rejected' ? 'Resubmit Delivery Photo' : 'Submit Delivery Photo' }}</h2>
                @if($order->deliveryProof?->status === 'rejected')
                    <div class="mb-4 rounded-lg bg-status-danger-bg px-3 py-2 text-sm text-status-danger">
                        The admin denied the previous photo. Please submit a new one.
                        @if($order->deliveryProof->review_note)
                            <div class="mt-1">Reason: {{ $order->deliveryProof->review_note }}</div>
                        @endif
                    </div>
                @endif
                <p class="text-xs text-gray-500 mb-4">Upload a photo proof (e.g. handed-off cylinder / signed receipt). The admin must approve the photo before the order is marked delivered.</p>
                @unless($deliveryDateDue)
                    <p class="text-sm text-status-warning mb-3">This order is scheduled for {{ $order->scheduled_delivery_date?->format('M j, Y') ?? 'a date not yet set' }}. Delivery completion is available on or after that date.</p>
                @endunless
                <form method="POST" action="{{ route('delivery.orders.complete', $order) }}" enctype="multipart/form-data"
                      x-data="{ proofName: '', clearProof() { this.$refs.proofInput.value = ''; this.proofName = ''; } }"
                      onsubmit="return confirm('Send this delivery photo to the admin for review?');" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Proof photo</label>
                        <input x-ref="proofInput" @change="proofName = $event.target.files.length ? $event.target.files[0].name : ''" type="file" name="proof_image" accept="image/*" required
                               class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-maroon-50 file:text-maroon-700 file:text-xs file:font-medium">
                        <div class="flex items-center justify-between gap-3 mt-2" x-show="proofName" x-cloak>
                            <p class="min-w-0 truncate text-xs text-gray-500" x-text="proofName"></p>
                            <button type="button" @click="clearProof()" class="shrink-0 text-xs font-medium text-status-danger hover:underline">Remove selected photo</button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Remarks (optional)</label>
                        <input type="text" name="remarks" class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    </div>
                    <button type="submit" @disabled(!$deliveryDateDue) class="w-full bg-status-success text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">
                        {{ $deliveryDateDue ? 'Mark as Delivered & Send Photo' : 'Available on Scheduled Date' }}
                    </button>
                </form>
            </div>
            @elseif($order->deliveryProof?->status === 'approved')
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <h2 class="font-semibold text-gray-900 mb-3">Approved Delivery Photo</h2>
                <img src="{{ asset('storage/' . $order->deliveryProof->proof_image_path) }}" alt="Delivery proof" class="rounded-lg border border-gray-200 max-h-80 object-cover">
                <p class="text-xs text-gray-400 mt-2">Approved {{ $order->deliveryProof->reviewed_at?->format('M j, Y g:i A') }}</p>
            </div>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm h-fit">
            <h2 class="font-semibold text-gray-900 mb-3">Customer</h2>
            <p class="text-sm text-gray-800">{{ $order->customer->name }}</p>
            <p class="text-sm text-gray-500">{{ $order->customer->phone }}</p>
            <p class="text-sm text-gray-500 mt-1">{{ $order->delivery_address }}</p>
            <div class="text-xs text-gray-500 mt-3">Scheduled delivery: {{ $order->scheduled_delivery_date?->format('M j, Y') ?? 'Not set' }}</div>
            <div class="text-xs text-gray-400 mt-1">Ordered: {{ $order->ordered_at?->format('M j, Y g:i A') }}</div>
            <div class="mt-3 pt-3 border-t border-gray-100 text-sm">
                <span class="text-gray-500">Payment status:</span>
                <x-badge :color="$order->payment_status === 'paid_in_full' ? 'green' : 'yellow'" :label="$order->payment_status_label" class="mt-1" />
            </div>
            <div class="text-xs text-gray-400 mt-3 pt-3 border-t border-gray-100">Order date: {{ $order->order_date->format('M j, Y') }}</div>
        </div>
    </div>

</x-app-layout>
