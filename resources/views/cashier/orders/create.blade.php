@php
$productsForJs = $products->map(fn ($p) => [
'id' => $p->id,
'name' => $p->name,
'cylinder_name' => $p->cylinder?->name,
'gas_price' => $p->gas_price,
'current_stock' => $p->current_stock,
'cylinder_price' => $p->cylinder?->is_active ? $p->cylinder->price : null,
]);
@endphp

<x-app-layout title="New Order" header="Take New Order">

    <div class="max-w-4xl" x-data="orderForm({{ \Illuminate\Support\Js::from($productsForJs) }})">

        <form method="POST" action="{{ route('cashier.orders.store') }}" @submit="if (items.length === 0) { $event.preventDefault(); alert('Add at least one product.'); }">
            @csrf

            {{-- Customer & delivery --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mb-5">
                <h2 class="font-semibold text-gray-900 mb-4">Customer &amp; Fulfillment</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Customer name</label>
                        <input type="text" name="customer_name" value="{{ old('customer_name') }}" required
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Phone number</label>
                        <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" required
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Order type</label>
                        <div class="flex gap-5 mt-2">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="fulfillment_type" value="delivery" x-model="fulfillmentType" @checked(old('fulfillment_type', 'delivery') === 'delivery') class="text-maroon-600 focus:ring-maroon-500"> Delivery
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="fulfillment_type" value="pickup" x-model="fulfillmentType" @checked(old('fulfillment_type') === 'pickup') class="text-maroon-600 focus:ring-maroon-500"> Pick-up
                            </label>
                        </div>
                    </div>
                    <div class="sm:col-span-2" x-show="fulfillmentType === 'delivery'" x-cloak>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Delivery address</label>
                        <input type="text" name="delivery_address" value="{{ old('delivery_address') }}" :required="fulfillmentType === 'delivery'"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    </div>
                    <div x-show="fulfillmentType === 'delivery'" x-cloak>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Scheduled delivery date</label>
                        <input type="date" name="scheduled_delivery_date" value="{{ old('scheduled_delivery_date') }}" min="{{ now()->toDateString() }}" :required="fulfillmentType === 'delivery'"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Order date</label>
                        <input type="text" disabled value="{{ now()->format('F j, Y g:i A') }}"
                            class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-500">
                        <p class="text-[11px] text-gray-400 mt-1">Date and order time are recorded automatically.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Payment method</label>
                        <div class="flex gap-4 mt-2">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="payment_method" value="cash" checked class="text-maroon-600 focus:ring-maroon-500"> Cash
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="payment_method" value="gcash" class="text-maroon-600 focus:ring-maroon-500"> GCash
                            </label>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">Full payment only — no down payment or partial payment.</p>
                    </div>
                </div>
            </div>

            {{-- Line items --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mb-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-900">Products</h2>
                    <button type="button" @click="addItem()" class="text-xs font-medium text-maroon-600 hover:underline">+ Add product</button>
                </div>

                <template x-for="(item, index) in items" :key="index">
                    <div class="grid grid-cols-12 gap-3 items-end border-b border-gray-100 pb-4 mb-4 last:border-0 last:pb-0 last:mb-0">
                        <div class="col-span-12 sm:col-span-5">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Product</label>
                            <select :name="`items[${index}][product_id]`" x-model="item.product_id" required
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                                <option value="">Select product…</option>
                                <template x-for="p in products" :key="p.id">
                                    <option :value="p.id" x-text="`${p.name} (${p.cylinder_name || 'size not set'}) — gas ₱${Number(p.gas_price).toLocaleString()}, cylinder ₱${Number(p.cylinder_price || 0).toLocaleString()} (stock: ${p.current_stock})`"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-span-4 sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Qty</label>
                            <input type="number" min="1" :name="`items[${index}][quantity]`" x-model.number="item.quantity" required
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                        </div>
                        <div class="col-span-8 sm:col-span-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Cylinder option</label>
                            <select :name="`items[${index}][has_own_cylinder]`" x-model="item.has_own_cylinder"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                                <option :value="true">Gas only (exchange)</option>
                                <option :value="false"
                                    x-text="!findProduct(item.product_id) ? 'Select gas first' : findProduct(item.product_id).cylinder_price == null ? 'Cylinder size not configured' : `Buy ${findProduct(item.product_id).cylinder_name} cylinder — ₱${Number(findProduct(item.product_id).cylinder_price).toLocaleString()}`"></option>
                            </select>
                        </div>
                        <div class="col-span-8 sm:col-span-1 text-sm text-gray-700 font-medium" x-text="'₱' + lineTotal(item).toLocaleString()"></div>
                        <div class="col-span-4 sm:col-span-1 text-right">
                            <button type="button" @click="removeItem(index)" class="text-status-danger text-xs hover:underline">Remove</button>
                        </div>
                    </div>
                </template>

                <p x-show="items.length === 0" class="text-sm text-gray-400 text-center py-4">No products added yet.</p>
            </div>

            {{-- Totals --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mb-5">
                <div class="flex justify-between text-sm text-gray-600 mb-1">
                    <span>Subtotal (gas)</span>
                    <span x-text="'₱' + subtotal().toLocaleString()"></span>
                </div>
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>Cylinder purchases</span>
                    <span x-text="'₱' + cylinderFeeTotal().toLocaleString()"></span>
                </div>
                <div class="flex justify-between text-base font-semibold text-gray-900 pt-2 border-t border-gray-100">
                    <span>Grand total</span>
                    <span x-text="'₱' + grandTotal().toLocaleString()"></span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Notes (optional)</label>
                <textarea name="notes" rows="2" class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">{{ old('notes') }}</textarea>
            </div>

            <div class="mt-5 flex gap-3">
                <button type="submit" class="bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                    Confirm Order
                </button>
                <a href="{{ route('cashier.orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700 px-2 py-2.5">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        function orderForm(products) {
            return {
                products,
                fulfillmentType: @js(old('fulfillment_type', 'delivery')),
                items: [{
                    product_id: '',
                    quantity: 1,
                    has_own_cylinder: true
                }],
                addItem() {
                    this.items.push({
                        product_id: '',
                        quantity: 1,
                        has_own_cylinder: true
                    });
                },
                removeItem(index) {
                    this.items.splice(index, 1);
                },
                findProduct(id) {
                    return this.products.find(p => p.id == id);
                },
                lineTotal(item) {
                    const p = this.findProduct(item.product_id);
                    if (!p || !item.quantity) return 0;
                    const fee = (item.has_own_cylinder === true || item.has_own_cylinder === 'true') ? 0 : parseFloat(p.cylinder_price || 0);
                    return (parseFloat(p.gas_price) + fee) * item.quantity;
                },
                subtotal() {
                    return this.items.reduce((sum, item) => {
                        const p = this.findProduct(item.product_id);
                        return sum + (p ? parseFloat(p.gas_price) * (item.quantity || 0) : 0);
                    }, 0);
                },
                cylinderFeeTotal() {
                    return this.items.reduce((sum, item) => {
                        const p = this.findProduct(item.product_id);
                        if (!p) return sum;
                        const owns = (item.has_own_cylinder === true || item.has_own_cylinder === 'true');
                        return sum + (owns ? 0 : parseFloat(p.cylinder_price || 0) * (item.quantity || 0));
                    }, 0);
                },
                grandTotal() {
                    return this.subtotal() + this.cylinderFeeTotal();
                },
            };
        }
    </script>

</x-app-layout>