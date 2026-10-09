<x-app-layout title="{{ $inventoryItemName }} Audit" header="Inventory Audit — {{ $inventoryItemName }}">

    <div class="mb-4">
        <a href="{{ route($backRoute) }}" class="text-sm text-maroon-600 hover:underline">← Back to Inventory</a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="py-3 px-4 font-medium">Date</th>
                        <th class="py-3 px-4 font-medium">Type</th>
                        <th class="py-3 px-4 font-medium">Change</th>
                        <th class="py-3 px-4 font-medium">Stock after</th>
                        <th class="py-3 px-4 font-medium">Order</th>
                        <th class="py-3 px-4 font-medium">By</th>
                        <th class="py-3 px-4 font-medium">Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="py-2.5 px-4 text-gray-500">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                        <td class="py-2.5 px-4">
                            <x-badge :color="$log->type === 'sale' ? 'red' : ($log->type === 'restock' ? 'green' : 'yellow')" :label="ucfirst($log->type)" />
                        </td>
                        <td class="py-2.5 px-4 {{ $log->quantity_change < 0 ? 'text-status-danger' : 'text-status-success' }} font-medium">
                            {{ $log->quantity_change > 0 ? '+' : '' }}{{ $log->quantity_change }}
                        </td>
                        <td class="py-2.5 px-4 text-gray-700">{{ $log->stock_after }}</td>
                        <td class="py-2.5 px-4 text-gray-500">{{ $log->order->order_code ?? '—' }}</td>
                        <td class="py-2.5 px-4 text-gray-500">{{ $log->creator->name ?? '—' }}</td>
                        <td class="py-2.5 px-4 text-gray-500">{{ $log->note }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-gray-400">No inventory movement yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $logs->links() }}</div>
        @endif
    </div>

</x-app-layout>