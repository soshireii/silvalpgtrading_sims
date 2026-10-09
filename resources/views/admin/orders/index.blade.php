<x-app-layout title="Orders" header="Orders">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <form method="GET" class="flex flex-wrap gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search order #, name or phone…"
                   class="rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500 w-64">
            <select name="status" class="rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                <option value="">All statuses</option>
                @foreach(['pending' => 'Pending', 'out_for_delivery' => 'Out for Delivery', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $val => $label)
                    <option value="{{ $val }}" @selected(request('status') === $val)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="proof_status" class="rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                <option value="">All delivery photos</option>
                <option value="pending" @selected(request('proof_status') === 'pending')>Awaiting review ({{ $pendingDeliveryReviews }})</option>
            </select>
            <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg">Filter</button>
            @if(request('search') || request('status') || request('proof_status'))
                <a href="{{ route('admin.orders.index') }}" class="text-sm text-gray-400 hover:text-gray-600 px-2 py-2">Clear</a>
            @endif
        </form>

        <a href="{{ route('admin.orders.create') }}" class="inline-flex items-center gap-2 bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg shadow-sm transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Take New Order
        </a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="py-3 px-4 font-medium">Order</th>
                        <th class="py-3 px-4 font-medium">Customer</th>
                        <th class="py-3 px-4 font-medium">Date</th>
                        <th class="py-3 px-4 font-medium">Total</th>
                        <th class="py-3 px-4 font-medium">Payment</th>
                        <th class="py-3 px-4 font-medium">Delivery</th>
                        <th class="py-3 px-4 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="py-3 px-4"><a href="{{ route('admin.orders.show', $order) }}" class="text-maroon-600 font-medium hover:underline">{{ $order->order_code }}</a></td>
                        <td class="py-3 px-4 text-gray-700">{{ $order->customer->name }}</td>
                        <td class="py-3 px-4 text-gray-500">
                            <div>{{ $order->order_date->format('M j, Y') }}</div>
                            <div class="text-xs text-gray-400">{{ $order->ordered_at?->format('g:i A') }}</div>
                        </td>
                        <td class="py-3 px-4 text-gray-700">₱{{ number_format($order->grand_total, 2) }}</td>
                        <td class="py-3 px-4">
                            <div class="text-gray-500 capitalize">{{ $order->payment_method }}</div>
                            <x-badge :color="$order->payment_status === 'paid_in_full' ? 'green' : 'yellow'" :label="$order->payment_status_label" class="mt-1" />
                            @if($order->deliveryProof?->status === 'pending')
                                <div class="mt-1 text-xs font-medium text-status-warning">Delivery photo needs review</div>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-gray-500">
                            <div>{{ $order->fulfillment_type === 'pickup' ? 'Pick-up' : 'Delivery' }}</div>
                            @if($order->fulfillment_type === 'delivery')
                                <div class="text-xs text-gray-400">Scheduled: {{ $order->scheduled_delivery_date?->format('M j, Y') ?? 'Not set' }}</div>
                                <div class="text-xs">{{ $order->deliveryStaff->name ?? 'Unassigned' }}</div>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            <x-badge :color="$order->is_delayed ? 'yellow' : $order->status_color" :label="$order->is_delayed ? 'Delayed' : $order->status_label" />
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="py-8 text-center text-gray-400">No orders found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $orders->links() }}</div>
        @endif
    </div>

</x-app-layout>
