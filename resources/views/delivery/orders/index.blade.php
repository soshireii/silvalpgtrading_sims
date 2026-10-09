<x-app-layout title="My Deliveries" header="My Deliveries">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="py-3 px-4 font-medium">Order</th>
                        <th class="py-3 px-4 font-medium">Customer</th>
                        <th class="py-3 px-4 font-medium">Address</th>
                        <th class="py-3 px-4 font-medium">Scheduled / Ordered</th>
                        <th class="py-3 px-4 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="py-3 px-4"><a href="{{ route('delivery.orders.show', $order) }}" class="text-maroon-600 font-medium hover:underline">{{ $order->order_code }}</a></td>
                        <td class="py-3 px-4 text-gray-700">{{ $order->customer->name }}</td>
                        <td class="py-3 px-4 text-gray-500">{{ $order->delivery_address }}</td>
                        <td class="py-3 px-4 text-gray-500">
                            <div>{{ $order->scheduled_delivery_date?->format('M j, Y') ?? 'No date set' }}</div>
                            <div class="text-xs text-gray-400">Ordered {{ $order->ordered_at?->format('M j, Y g:i A') }}</div>
                        </td>
                        <td class="py-3 px-4">
                            <x-badge :color="$order->is_delayed ? 'yellow' : $order->status_color" :label="$order->is_delayed ? 'Delayed' : $order->status_label" />
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="py-8 text-center text-gray-400">No deliveries assigned to you.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $orders->links() }}</div>
        @endif
    </div>

</x-app-layout>
