{{--
    "Take New Order" pop-up, shared by the owner and cashier order pages.
    Needs: $products (active gas products with cylinder) and $storeRoute (route name of the POST).
    Opens from any button that dispatches 'open-modal' => 'new-order', or automatically
    when the page is loaded with ?new=1 or the form comes back with validation errors.
--}}
@php
    $productsForJs = $products->map(fn ($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'cylinder_name' => $p->cylinder?->name,
        'gas_price' => $p->gas_price,
        'current_stock' => $p->current_stock,
        'cylinder_price' => $p->cylinder?->is_active ? $p->cylinder->price : null,
    ]);
    $startItems = old('items')
        ? array_values(old('items'))
        : [['product_id' => '', 'quantity' => 1, 'has_own_cylinder' => true]];
@endphp

<x-modal name="new-order" :show="$errors->any() || request()->boolean('new')" maxWidth="4xl" focusable>
    <form method="POST" action="{{ route($storeRoute) }}"
          x-data="orderForm({{ \Illuminate\Support\Js::from($productsForJs) }}, {{ \Illuminate\Support\Js::from($startItems) }}, @js(old('fulfillment_type', 'delivery')))"
          @submit="if (items.length === 0) { $event.preventDefault(); alert('Add at least one product.'); }"
          class="flex max-h-[90vh] flex-col">
        @csrf

        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h2 class="font-semibold text-gray-900">Take New Order</h2>
            <button type="button" x-on:click="$dispatch('close-modal', 'new-order')"
                    class="text-2xl leading-none text-gray-400 hover:text-gray-600" aria-label="Close">&times;</button>
        </div>

        <div class="space-y-5 overflow-y-auto px-6 py-5">

            @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-status-danger-bg px-4 py-3 text-sm font-medium text-status-danger">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- Customer & fulfillment --}}
            <div>
                <h3 class="mb-3 text-sm font-semibold text-gray-900">Customer &amp; Fulfillment</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">First name</label>
                        <input type="text" name="customer_first_name" value="{{ old('customer_first_name') }}" required maxlength="100"
                               class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Last name</label>
                        <input type="text" name="customer_last_name" value="{{ old('customer_last_name') }}" required maxlength="100"
                               class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Phone number</label>
                        <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" required maxlength="30"
                               class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Order type</label>
                        <div class="mt-2 flex gap-5">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="fulfillment_type" value="delivery" x-model="fulfillmentType" class="text-maroon-600 focus:ring-maroon-500"> Delivery
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="fulfillment_type" value="pickup" x-model="fulfillmentType" class="text-maroon-600 focus:ring-maroon-500"> Pick-up
                            </label>
                        </div>
                    </div>
                    <div class="sm:col-span-2" x-show="fulfillmentType === 'delivery'" x-cloak>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Delivery address</label>
                        <input type="text" name="delivery_address" value="{{ old('delivery_address') }}" maxlength="255" :required="fulfillmentType === 'delivery'"
                               class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    </div>
                    <div x-show="fulfillmentType === 'delivery'" x-cloak>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Scheduled delivery date</label>
                        <input type="date" name="scheduled_delivery_date" value="{{ old('scheduled_delivery_date') }}" min="{{ now()->toDateString() }}" :required="fulfillmentType === 'delivery'"
                               class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Payment method</label>
                        <div class="mt-2 flex gap-4">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="payment_method" value="cash" @checked(old('payment_method', 'cash') === 'cash') class="text-maroon-600 focus:ring-maroon-500"> Cash
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="payment_method" value="gcash" @checked(old('payment_method') === 'gcash') class="text-maroon-600 focus:ring-maroon-500"> GCash
                            </label>
                        </div>
                        <p class="mt-1 text-[11px] text-gray-400">Full payment only — no down payment or partial payment.</p>
                    </div>
                </div>
            </div>

            {{-- Line items --}}
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Products</h3>
                    <button type="button" @click="addItem()" class="text-xs font-medium text-maroon-600 hover:underline">+ Add product</button>
                </div>

                <template x-for="(item, index) in items" :key="index">
                    <div class="mb-4 grid grid-cols-12 items-end gap-3 border-b border-gray-100 pb-4 last:mb-0 last:border-0 last:pb-0">
                        <div class="col-span-12 sm:col-span-5">
                            <label class="mb-1 block text-xs font-medium text-gray-600">Product</label>
                            <select :name="`items[${index}][product_id]`" x-model="item.product_id" required
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                                <option value="">Select product…</option>
                                <template x-for="p in products" :key="p.id">
                                    <option :value="p.id" x-text="`${p.name} (${p.cylinder_name || 'size not set'}) — gas ₱${Number(p.gas_price).toLocaleString()} (stock: ${p.current_stock})`"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-span-4 sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gray-600">Qty</label>
                            <input type="number" min="1" :name="`items[${index}][quantity]`" x-model.number="item.quantity" required
                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                        </div>
                        <div class="col-span-8 sm:col-span-3">
                            <label class="mb-1 block text-xs font-medium text-gray-600">Cylinder option</label>
                            <select :name="`items[${index}][has_own_cylinder]`" x-model="item.has_own_cylinder"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                                <option :value="true">Gas only (exchange)</option>
                                <option :value="false"
                                        x-text="!findProduct(item.product_id) ? 'Select gas first' : findProduct(item.product_id).cylinder_price == null ? 'Cylinder size not configured' : `Buy ${findProduct(item.product_id).cylinder_name} cylinder — ₱${Number(findProduct(item.product_id).cylinder_price).toLocaleString()}`"></option>
                            </select>
                        </div>
                        <div class="col-span-8 text-sm font-medium text-gray-700 sm:col-span-1" x-text="'₱' + lineTotal(item).toLocaleString()"></div>
                        <div class="col-span-4 text-right sm:col-span-1">
                            <button type="button" @click="removeItem(index)" class="text-xs text-status-danger hover:underline">Remove</button>
                        </div>
                    </div>
                </template>

                <p x-show="items.length === 0" class="py-4 text-center text-sm text-gray-400">No products added yet.</p>
            </div>

            {{-- Totals --}}
            <div class="rounded-lg bg-gray-50 px-4 py-3">
                <div class="mb-1 flex justify-between text-sm text-gray-600">
                    <span>Subtotal (gas)</span>
                    <span x-text="'₱' + subtotal().toLocaleString()"></span>
                </div>
                <div class="mb-2 flex justify-between text-sm text-gray-600">
                    <span>Cylinder purchases</span>
                    <span x-text="'₱' + cylinderFeeTotal().toLocaleString()"></span>
                </div>
                <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-semibold text-gray-900">
                    <span>Grand total</span>
                    <span x-text="'₱' + grandTotal().toLocaleString()"></span>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Notes (optional)</label>
                <textarea name="notes" rows="2" class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">{{ old('notes') }}</textarea>
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4">
            <button type="button" x-on:click="$dispatch('close-modal', 'new-order')"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
            <button type="submit" class="rounded-lg bg-maroon-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-maroon-700">Confirm Order</button>
        </div>
    </form>
</x-modal>

<script>
    function orderForm(products, startItems, startFulfillment) {
        return {
            products,
            fulfillmentType: startFulfillment,
            items: startItems,
            addItem() {
                this.items.push({ product_id: '', quantity: 1, has_own_cylinder: true });
            },
            removeItem(index) {
                this.items.splice(index, 1);
            },
            findProduct(id) {
                return this.products.find(p => p.id == id);
            },
            owns(item) {
                return item.has_own_cylinder === true || item.has_own_cylinder === 'true';
            },
            lineTotal(item) {
                const p = this.findProduct(item.product_id);
                if (!p || !item.quantity) return 0;
                const fee = this.owns(item) ? 0 : parseFloat(p.cylinder_price || 0);
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
                    if (!p || this.owns(item)) return sum;
                    return sum + parseFloat(p.cylinder_price || 0) * (item.quantity || 0);
                }, 0);
            },
            grandTotal() {
                return this.subtotal() + this.cylinderFeeTotal();
            },
        };
    }
</script>
