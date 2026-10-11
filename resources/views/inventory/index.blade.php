{{--
    Shared inventory page (owner + cashier).
    $prefix = 'admin' | 'cashier'; $isAdmin gates product/supplier management links.
--}}
<x-app-layout title="Inventory" header="Inventory">

    <div x-data="{
            adjust: {
                id: @js(old('_product')),
                name: @js($products->firstWhere('id', (int) old('_product'))?->name ?? ''),
                direction: @js(old('_direction', 'out')),
            },
            outUrl: @js(route('stock.out', ['product' => '__ID__'])),
            inUrl: @js(route('stock.in', ['product' => '__ID__'])),
            fire(name, detail) { window.dispatchEvent(new CustomEvent(name, { detail: detail })); },
            openStockIn(id) {
                this.fire('open-modal', 'new-stock-in');
                setTimeout(() => this.fire('stock-in-product', id), 50);
            },
            openAdjust(id, name, direction = 'out') {
                this.adjust = { id: id, name: name, direction: direction };
                this.fire('open-modal', 'adjust-stock');
            },
            adjustAction() { return (this.adjust.direction === 'out' ? this.outUrl : this.inUrl).replace('__ID__', this.adjust.id); },
        }">

        @php
            $low = $products->filter(fn($p) => $p->stock_status !== 'green');
            $stockedUnits = $products->sum('current_stock');
        @endphp

        @if($low->isNotEmpty())
        <div class="mb-5 bg-status-warning-bg border border-yellow-200 text-status-warning text-sm font-medium px-4 py-3 rounded-lg">
            ⚠ {{ $low->count() }} gas product(s) at or below their reorder level — order more from a supplier.
        </div>
        @endif

        {{-- Actions --}}
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2">
                <button type="button" x-on:click="$dispatch('open-modal', 'new-stock-in')"
                        class="inline-flex items-center gap-2 bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg shadow-sm transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    Stock In from Supplier
                </button>
                <a href="{{ route('purchase-orders.index') }}" class="inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium px-4 py-2.5 rounded-lg transition">
                    Supplier orders
                    @if($openPurchaseOrders->isNotEmpty())
                    <span class="rounded-full bg-status-warning-bg px-2 py-0.5 text-xs text-status-warning">{{ $openPurchaseOrders->count() }} waiting</span>
                    @endif
                </a>
            </div>
            @if($isAdmin)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.suppliers.index') }}" class="inline-flex items-center border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium px-4 py-2.5 rounded-lg transition">Suppliers</a>
                <a href="{{ route('admin.cylinders.index') }}" class="inline-flex items-center border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium px-4 py-2.5 rounded-lg transition">Manage cylinders</a>
                <a href="{{ route('admin.inventory.create') }}" class="inline-flex items-center border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium px-4 py-2.5 rounded-lg transition">Add gas</a>
            </div>
            @endif
        </div>

        {{-- Orders waiting for the supplier to deliver --}}
        @if($openPurchaseOrders->isNotEmpty())
        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 font-semibold text-gray-900">Waiting for delivery</h2>
            <div class="divide-y divide-gray-100">
                @foreach($openPurchaseOrders as $po)
                <div class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                    <div>
                        <a href="{{ route('purchase-orders.show', $po) }}" class="font-medium text-maroon-600 hover:underline">{{ $po->po_number }}</a>
                        <span class="text-sm text-gray-500"> · {{ $po->supplier->name }}</span>
                        <p class="text-xs text-gray-400">
                            {{ $po->items->map(fn($i) => $i->quantity_ordered . '× ' . $i->product->name)->join(', ') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        @if($po->is_overdue)
                        <x-badge color="yellow" label="Overdue" />
                        @elseif($po->expected_date)
                        <span class="text-xs text-gray-500">Expected {{ $po->expected_date->format('M j') }}</span>
                        @endif
                        <a href="{{ route('purchase-orders.show', $po) }}" class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-200">Check in delivery</a>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Stock per gas --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @foreach($products as $product)
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ $product->name }}</h3>
                        <p class="text-xs text-gray-400">{{ $product->cylinder?->name }} · Gas ₱{{ number_format($product->gas_price, 2) }} · Cylinder ₱{{ number_format($product->cylinder?->price ?? 0, 2) }}</p>
                    </div>
                    <x-badge :color="$product->stock_status" :label="$product->stock_label" />
                </div>

                <div class="flex justify-between text-sm text-gray-600 mb-1 mt-3">
                    <span>{{ $product->current_stock }} / {{ $product->max_capacity }} gas units</span>
                    <span class="text-gray-400">reorder at {{ $product->reorder_level }}</span>
                </div>
                <div class="w-full h-2.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full {{ $product->stock_status === 'green' ? 'bg-status-success' : ($product->stock_status === 'yellow' ? 'bg-status-warning' : 'bg-status-danger') }}"
                         style="width: {{ max($product->stock_percent, 4) }}%"></div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 mt-4 pt-4 border-t border-gray-100">
                    <div class="flex gap-2">
                        <button type="button" x-on:click="openStockIn({{ $product->id }})"
                                class="rounded-lg bg-status-success-bg px-3 py-2 text-xs font-medium text-status-success hover:opacity-80">+ Stock In</button>
                        <button type="button" x-on:click="openAdjust({{ $product->id }}, @js($product->name), 'out')"
                                class="rounded-lg bg-status-danger-bg px-3 py-2 text-xs font-medium text-status-danger hover:opacity-80">− Stock Out</button>
                    </div>
                    <div class="flex gap-3 text-xs">
                        <a href="{{ route($prefix . '.inventory.logs', $product) }}" class="text-maroon-600 hover:underline">History →</a>
                        @if($isAdmin)
                        <a href="{{ route('admin.inventory.edit', $product) }}" class="text-maroon-600 hover:underline">Edit gas</a>
                        <form method="POST" action="{{ route('admin.inventory.destroy', $product) }}" onsubmit="return confirm('Delete this gas record? Records with stock, order or supplier history cannot be deleted.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-status-danger hover:underline">Delete</button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Latest stock movements --}}
        <div class="mt-8 rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Latest stock in &amp; out</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-gray-500 border-b border-gray-100">
                            <th class="py-3 px-4 font-medium">When</th>
                            <th class="py-3 px-4 font-medium">Gas</th>
                            <th class="py-3 px-4 font-medium">Movement</th>
                            <th class="py-3 px-4 font-medium">Qty</th>
                            <th class="py-3 px-4 font-medium">Reason</th>
                            <th class="py-3 px-4 font-medium">By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentMovements as $m)
                        <tr class="border-b border-gray-50">
                            <td class="py-2.5 px-4 text-gray-500">{{ $m->created_at->format('M j, g:i A') }}</td>
                            <td class="py-2.5 px-4 text-gray-800">{{ $m->product->name }}</td>
                            <td class="py-2.5 px-4"><x-badge :color="$m->type === 'in' ? 'green' : 'red'" :label="$m->type_label" /></td>
                            <td class="py-2.5 px-4 font-medium {{ $m->type === 'in' ? 'text-status-success' : 'text-status-danger' }}">{{ $m->quantity_change > 0 ? '+' : '' }}{{ $m->quantity_change }}</td>
                            <td class="py-2.5 px-4 text-gray-600">{{ $m->reason_label }}</td>
                            <td class="py-2.5 px-4 text-gray-500">{{ $m->creator->name ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="py-6 text-center text-gray-400">No stock movement yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Cylinder sizes --}}
        <h2 class="font-semibold text-gray-900 mt-8 mb-3">Cylinder sizes</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($cylinders as $cylinder)
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <h3 class="font-semibold text-gray-900">{{ $cylinder->name }}</h3>
                <p class="text-xs text-gray-400">Cylinder price ₱{{ number_format($cylinder->price, 2) }}</p>
                <p class="text-xs text-gray-500 mt-2">{{ $cylinder->products->pluck('name')->join(', ') ?: 'No gas uses this size yet.' }}</p>
            </div>
            @endforeach
        </div>

        {{-- Stock Out / count correction --}}
        <x-modal name="adjust-stock" :show="old('_modal') === 'adjust-stock' && $errors->any()" maxWidth="md" focusable>
            <form method="POST" :action="adjustAction()" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="_modal" value="adjust-stock">
                <input type="hidden" name="_product" :value="adjust.id">
                <input type="hidden" name="_direction" :value="adjust.direction">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900" x-text="adjust.direction === 'out' ? 'Stock Out — ' + adjust.name : 'Add stock — ' + adjust.name"></h2>
                    <button type="button" x-on:click="$dispatch('close-modal', 'adjust-stock')" class="text-2xl leading-none text-gray-400 hover:text-gray-600" aria-label="Close">&times;</button>
                </div>

                @if($errors->any() && old('_modal') === 'adjust-stock')
                <div class="rounded-lg border border-red-200 bg-status-danger-bg px-4 py-3 text-sm font-medium text-status-danger">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
                @endif

                <div class="flex gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" value="out" x-model="adjust.direction" class="text-maroon-600 focus:ring-maroon-500"> Take out</label>
                    <label class="flex items-center gap-2"><input type="radio" value="in" x-model="adjust.direction" class="text-maroon-600 focus:ring-maroon-500"> Add (count correction)</label>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Quantity</label>
                    <input type="number" name="quantity" min="1" required value="{{ old('quantity') }}"
                           class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>

                <div x-show="adjust.direction === 'out'">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Reason</label>
                    <select name="reason" :disabled="adjust.direction !== 'out'" class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                        @foreach($outReasons as $value => $label)
                        <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-gray-400">Sales are recorded automatically from orders — don't enter them here.</p>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Note (optional)</label>
                    <input type="text" name="note" maxlength="255" value="{{ old('note') }}"
                           class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'adjust-stock')" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg">Save</button>
                </div>
            </form>
        </x-modal>

        @include('purchase-orders._new-modal', ['suppliers' => $suppliers, 'products' => $products])
    </div>

</x-app-layout>
