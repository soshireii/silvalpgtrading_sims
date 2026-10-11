<x-app-layout title="Supplier Orders" header="Supplier Orders (Stock In)">

    <div class="mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap gap-2">
            <select name="status" class="rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                <option value="">All orders</option>
                @foreach(['ordered' => 'Waiting for delivery', 'received' => 'Received', 'cancelled' => 'Cancelled'] as $val => $label)
                <option value="{{ $val }}" @selected(request('status') === $val)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg">Filter</button>
            @if(request('status'))
            <a href="{{ route('purchase-orders.index') }}" class="text-sm text-gray-400 hover:text-gray-600 px-2 py-2">Clear</a>
            @endif
        </form>

        <button type="button" x-data x-on:click="$dispatch('open-modal', 'new-stock-in')"
                class="inline-flex items-center gap-2 bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg shadow-sm transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
            Stock In from Supplier
        </button>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="py-3 px-4 font-medium">Order</th>
                        <th class="py-3 px-4 font-medium">Supplier</th>
                        <th class="py-3 px-4 font-medium">Gas</th>
                        <th class="py-3 px-4 font-medium">Ordered</th>
                        <th class="py-3 px-4 font-medium">Expected</th>
                        <th class="py-3 px-4 font-medium">Cost</th>
                        <th class="py-3 px-4 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="py-3 px-4"><a href="{{ route('purchase-orders.show', $order) }}" class="text-maroon-600 font-medium hover:underline">{{ $order->po_number }}</a></td>
                        <td class="py-3 px-4 text-gray-700">{{ $order->supplier->name }}</td>
                        <td class="py-3 px-4 text-gray-500">{{ $order->items->map(fn($i) => $i->quantity_ordered . '× ' . $i->product->name)->join(', ') }}</td>
                        <td class="py-3 px-4 text-gray-500">{{ $order->order_date->format('M j, Y') }}</td>
                        <td class="py-3 px-4 text-gray-500">{{ $order->expected_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="py-3 px-4 text-gray-700">₱{{ number_format($order->total_cost, 2) }}</td>
                        <td class="py-3 px-4">
                            <x-badge :color="$order->is_overdue ? 'yellow' : $order->status_color" :label="$order->is_overdue ? 'Overdue' : $order->status_label" />
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="py-8 text-center text-gray-400">No supplier orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $orders->links() }}</div>
        @endif
    </div>

    @include('purchase-orders._new-modal', ['suppliers' => $suppliers, 'products' => $products])

</x-app-layout>
