<x-app-layout title="Dashboard" header="Delivery Dashboard">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
        <x-stat-card label="Assigned Deliveries" value="{{ $myOrders->count() }}" accent="maroon">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </x-stat-card>
        <x-stat-card label="Completed Today" value="{{ $completedToday }}" accent="green">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </x-stat-card>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mt-6">
        <h2 class="font-semibold text-gray-900 mb-4">Orders To Deliver</h2>

        @if($myOrders->isEmpty())
        <p class="text-sm text-gray-400 py-6 text-center">No deliveries assigned to you right now. 🎉</p>
        @else
        <div class="space-y-3">
            @foreach($myOrders as $order)
            <a href="{{ route('delivery.orders.show', $order) }}"
                class="block border border-gray-200 hover:border-maroon-300 hover:bg-maroon-50/40 rounded-lg p-4 transition">
                <div class="flex items-center justify-between mb-1">
                    <span class="font-medium text-gray-900">{{ $order->order_code }}</span>
                    <x-badge :color="$order->is_delayed ? 'yellow' : $order->status_color" :label="$order->is_delayed ? 'Delayed' : $order->status_label" />
                </div>
                <p class="text-sm text-gray-600">{{ $order->customer->name }} — {{ $order->customer->phone }}</p>
                <p class="text-sm text-gray-500">Scheduled: {{ $order->scheduled_delivery_date?->format('M j, Y') ?? 'Not set' }} — {{ $order->delivery_address }}</p>
                <p class="text-xs text-gray-400 mt-1">Ordered {{ $order->ordered_at?->format('M j, Y g:i A') }}</p>
            </a>
            @endforeach
        </div>
        @endif
    </div>

</x-app-layout>
