<x-app-layout title="Edit Gas" header="Edit Gas Product">
    <div class="max-w-lg bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <form method="POST" action="{{ route('admin.inventory.update', $product) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Gas product name</label>
                <input type="text" name="name" value="{{ old('name', $product->name) }}" required
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                @error('name')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Gas price (₱)</label>
                <input type="number" step="0.01" min="0" name="gas_price" value="{{ old('gas_price', $product->gas_price) }}" required
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                @error('gas_price')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Cylinder size</label>
                <select name="cylinder_id" required class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    @foreach($cylinders as $cylinder)
                    <option value="{{ $cylinder->id }}" @selected(old('cylinder_id', $product->cylinder_id) == $cylinder->id)>{{ $cylinder->name }} — cylinder ₱{{ number_format($cylinder->price, 2) }}</option>
                    @endforeach
                </select>
                @error('cylinder_id')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Gas stock</label>
                    <input type="number" min="0" name="current_stock" value="{{ old('current_stock', $product->current_stock) }}" required
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    @error('current_stock')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Max capacity</label>
                    <input type="number" min="1" name="max_capacity" value="{{ old('max_capacity', $product->max_capacity) }}" required
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Reorder level</label>
                    <input type="number" min="0" name="reorder_level" value="{{ old('reorder_level', $product->reorder_level) }}" required
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
            </div>
            <p class="text-xs text-gray-400">Changes to gas stock are added to the inventory audit log.</p>
            <div class="pt-2 flex gap-3">
                <button type="submit" class="bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg">Save Gas</button>
                <a href="{{ route('admin.inventory.index') }}" class="text-sm text-gray-500 px-2 py-2.5">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
