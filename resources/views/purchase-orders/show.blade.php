<x-app-layout title="{{ $order->po_number }}" header="Supplier Order {{ $order->po_number }}">

    <div class="mb-4">
        <a href="{{ route('purchase-orders.index') }}" class="text-sm text-maroon-600 hover:underline">← Back to supplier orders</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">

            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-900">Gas ordered</h2>
                    <x-badge :color="$order->is_overdue ? 'yellow' : $order->status_color" :label="$order->is_overdue ? 'Overdue' : $order->status_label" />
                </div>

                @if($order->status === 'ordered')
                <form method="POST" action="{{ route('purchase-orders.receive', $order) }}" class="space-y-4">
                    @csrf
                    <p class="text-xs text-gray-500">Count what the supplier actually delivered and enter it below. Only the quantities you enter are added to stock.</p>
                    @if($errors->any())
                    <div class="rounded-lg border border-red-200 bg-status-danger-bg px-4 py-3 text-sm font-medium text-status-danger">
                        @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                    @endif
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b border-gray-100">
                                <th class="py-2 pr-3 font-medium">Gas</th>
                                <th class="py-2 pr-3 font-medium">Ordered</th>
                                <th class="py-2 pr-3 font-medium">Cost each</th>
                                <th class="py-2 font-medium">Delivered</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                            <tr class="border-b border-gray-50">
                                <td class="py-2.5 pr-3 text-gray-800">{{ $item->product->name }}
                                    <span class="text-xs text-gray-400">(stock {{ $item->product->current_stock }}/{{ $item->product->max_capacity }})</span>
                                </td>
                                <td class="py-2.5 pr-3 text-gray-600">{{ $item->quantity_ordered }}</td>
                                <td class="py-2.5 pr-3 text-gray-600">₱{{ number_format($item->unit_cost, 2) }}</td>
                                <td class="py-2.5">
                                    <input type="number" min="0" name="received[{{ $item->id }}]" value="{{ old('received.' . $item->id, $item->quantity_ordered) }}" required
                                           class="w-24 rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="submit" class="bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg shadow-sm">
                        Check in delivery &amp; add to stock
                    </button>
                </form>
                @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100">
                            <th class="py-2 pr-3 font-medium">Gas</th>
                            <th class="py-2 pr-3 font-medium">Ordered</th>
                            <th class="py-2 pr-3 font-medium">Delivered</th>
                            <th class="py-2 pr-3 font-medium">Cost each</th>
                            <th class="py-2 font-medium text-right">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        @php $qty = $order->status === 'received' ? (int) $item->quantity_received : $item->quantity_ordered; @endphp
                        <tr class="border-b border-gray-50">
                            <td class="py-2.5 pr-3 text-gray-800">{{ $item->product->name }}</td>
                            <td class="py-2.5 pr-3 text-gray-600">{{ $item->quantity_ordered }}</td>
                            <td class="py-2.5 pr-3 text-gray-600">{{ $order->status === 'received' ? $item->quantity_received : '—' }}</td>
                            <td class="py-2.5 pr-3 text-gray-600">₱{{ number_format($item->unit_cost, 2) }}</td>
                            <td class="py-2.5 text-right text-gray-800 font-medium">₱{{ number_format($qty * $item->unit_cost, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif

                <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between text-base font-semibold text-gray-900">
                    <span>{{ $order->status === 'received' ? 'Total paid to supplier' : 'Estimated cost' }}</span>
                    <span>₱{{ number_format($order->total_cost, 2) }}</span>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <h2 class="font-semibold text-gray-900 mb-3">Supplier</h2>
                <p class="text-sm text-gray-800">{{ $order->supplier->name }}</p>
                @if($order->supplier->contact_name)<p class="text-sm text-gray-500">{{ $order->supplier->contact_name }}</p>@endif
                @if($order->supplier->phone)<p class="text-sm text-gray-500">{{ $order->supplier->phone }}</p>@endif
                <div class="mt-3 pt-3 border-t border-gray-100 text-xs text-gray-500 space-y-1">
                    <p>Ordered {{ $order->order_date->format('M j, Y') }} by {{ $order->orderedBy->name ?? '—' }}</p>
                    @if($order->expected_date)<p>Expected {{ $order->expected_date->format('M j, Y') }}</p>@endif
                    @if($order->received_at)<p>Received {{ $order->received_at->format('M j, Y g:i A') }} by {{ $order->receivedBy->name ?? '—' }}</p>@endif
                    @if($order->expense)<p class="text-status-success">Recorded as a "Supplier Restock" expense</p>@endif
                </div>
                @if($order->notes)<p class="mt-3 text-xs italic text-gray-500">{{ $order->notes }}</p>@endif
            </div>

            @if($order->status === 'ordered')
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <h2 class="font-semibold text-gray-900 mb-3">Cancel this order</h2>
                <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}" onsubmit="return confirm('Cancel this supplier order? No stock will be added.');">
                    @csrf
                    <button type="submit" class="w-full bg-status-danger-bg hover:bg-red-200 text-status-danger text-sm font-medium px-4 py-2.5 rounded-lg">Cancel order</button>
                </form>
            </div>
            @endif
        </div>
    </div>

</x-app-layout>
