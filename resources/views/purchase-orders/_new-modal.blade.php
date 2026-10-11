{{--
    "Stock In from Supplier" pop-up: order gas from a supplier, or record a delivery that already arrived.
    Needs: $suppliers (active) and $products. Used on the inventory page and the supplier-orders page.
    Open it with $dispatch('open-modal', 'new-stock-in'); to preselect a product also fire
    window event 'stock-in-product' with the product id as detail.
--}}
@php
    $startLines = old('items')
        ? array_values(old('items'))
        : [['product_id' => '', 'quantity' => 1, 'unit_cost' => '']];
    $productNames = $products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'stock' => $p->current_stock, 'max' => $p->max_capacity]);
@endphp

<x-modal name="new-stock-in" :show="old('_modal') === 'new-stock-in' && $errors->any() || request()->boolean('new')" maxWidth="3xl" focusable>
    <form method="POST" action="{{ route('purchase-orders.store') }}"
          x-data="{
              products: {{ \Illuminate\Support\Js::from($productNames) }},
              lines: {{ \Illuminate\Support\Js::from($startLines) }},
              receiveNow: @js((bool) old('receive_now')),
              add() { this.lines.push({ product_id: '', quantity: 1, unit_cost: '' }); },
              remove(i) { this.lines.splice(i, 1); },
              preselect(id) {
                  if (!this.lines.some(l => l.product_id == id)) {
                      const empty = this.lines.find(l => !l.product_id);
                      if (empty) { empty.product_id = id; } else { this.lines.push({ product_id: id, quantity: 1, unit_cost: '' }); }
                  }
              },
              total() { return this.lines.reduce((s, l) => s + (parseFloat(l.unit_cost) || 0) * (parseInt(l.quantity) || 0), 0); },
          }"
          x-on:stock-in-product.window="preselect($event.detail)"
          class="flex max-h-[90vh] flex-col">
        @csrf
        <input type="hidden" name="_modal" value="new-stock-in">

        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <div>
                <h2 class="font-semibold text-gray-900">Stock In from Supplier</h2>
                <p class="text-xs text-gray-500">Order gas from a supplier. Stock is added when you check the delivery in.</p>
            </div>
            <button type="button" x-on:click="$dispatch('close-modal', 'new-stock-in')"
                    class="text-2xl leading-none text-gray-400 hover:text-gray-600" aria-label="Close">&times;</button>
        </div>

        <div class="space-y-5 overflow-y-auto px-6 py-5">
            @if($errors->any() && old('_modal') === 'new-stock-in')
            <div class="rounded-lg border border-red-200 bg-status-danger-bg px-4 py-3 text-sm font-medium text-status-danger">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Supplier</label>
                    <select name="supplier_id" required class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                        <option value="">Select supplier…</option>
                        @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @if($suppliers->isEmpty())
                    <p class="mt-1 text-xs text-status-warning">No active suppliers yet — the owner can add one under Suppliers.</p>
                    @endif
                </div>
                <div x-show="!receiveNow">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Expected delivery date (optional)</label>
                    <input type="date" name="expected_date" value="{{ old('expected_date') }}" min="{{ now()->toDateString() }}"
                           class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
            </div>

            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Gas to order</h3>
                    <button type="button" @click="add()" class="text-xs font-medium text-maroon-600 hover:underline">+ Add gas</button>
                </div>
                <template x-for="(line, i) in lines" :key="i">
                    <div class="mb-3 grid grid-cols-12 items-end gap-3">
                        <div class="col-span-12 sm:col-span-6">
                            <label class="mb-1 block text-xs font-medium text-gray-600">Gas</label>
                            <select :name="`items[${i}][product_id]`" x-model="line.product_id" required
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                                <option value="">Select gas…</option>
                                <template x-for="p in products" :key="p.id">
                                    <option :value="p.id" x-text="`${p.name} (in stock ${p.stock}/${p.max})`"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-span-5 sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gray-600">Qty</label>
                            <input type="number" min="1" :name="`items[${i}][quantity]`" x-model.number="line.quantity" required
                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                        </div>
                        <div class="col-span-5 sm:col-span-3">
                            <label class="mb-1 block text-xs font-medium text-gray-600">Cost each (₱)</label>
                            <input type="number" min="0" step="0.01" :name="`items[${i}][unit_cost]`" x-model="line.unit_cost" required
                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                        </div>
                        <div class="col-span-2 sm:col-span-1 text-right">
                            <button type="button" @click="remove(i)" x-show="lines.length > 1" class="text-xs text-status-danger hover:underline">Remove</button>
                        </div>
                    </div>
                </template>
                <div class="flex justify-between rounded-lg bg-gray-50 px-4 py-3 text-sm">
                    <span class="text-gray-600">Estimated cost</span>
                    <span class="font-semibold text-gray-900" x-text="'₱' + total().toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Notes (optional)</label>
                <textarea name="notes" rows="2" maxlength="500" class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">{{ old('notes') }}</textarea>
            </div>

            <label class="flex items-start gap-3 rounded-lg border border-gray-200 px-4 py-3 text-sm">
                <input type="checkbox" name="receive_now" value="1" x-model="receiveNow" class="mt-0.5 rounded border-gray-300 text-maroon-600 focus:ring-maroon-500">
                <span>
                    <span class="font-medium text-gray-900">It has already been delivered</span>
                    <span class="block text-xs text-gray-500">Adds the gas to stock right away and records the cost as a "Supplier Restock" expense.</span>
                </span>
            </label>
        </div>

        <div class="flex justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4">
            <button type="button" x-on:click="$dispatch('close-modal', 'new-stock-in')"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
            <button type="submit" class="rounded-lg bg-maroon-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-maroon-700"
                    x-text="receiveNow ? 'Save & add to stock' : 'Place order'"></button>
        </div>
    </form>
</x-modal>
