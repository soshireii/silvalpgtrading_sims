<x-app-layout title="Add Gas" header="Add Gas Product">

    <div class="max-w-lg bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <form method="POST" action="{{ route('admin.inventory.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Gas product / brand name</label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. LPG Cylinder 11kg"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Gas price (₱)</label>
                <input type="number" step="0.01" min="0" name="gas_price" value="{{ old('gas_price') }}" required
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Cylinder size</label>
                <select name="cylinder_id" required class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    <option value="">Select a cylinder size…</option>
                    @foreach($cylinders as $cylinder)
                    <option value="{{ $cylinder->id }}" @selected(old('cylinder_id') == $cylinder->id)>{{ $cylinder->name }} — cylinder ₱{{ number_format($cylinder->price, 2) }}</option>
                    @endforeach
                </select>
                @error('cylinder_id')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
                @if($cylinders->isEmpty())
                    <p class="text-[11px] text-status-danger mt-1">Add a cylinder size in <a href="{{ route('admin.cylinders.create') }}" class="underline">Cylinder Inventory</a> before adding gas stock.</p>
                @else
                    <p class="text-[11px] text-gray-400 mt-1">Add or edit sizes and cylinder prices in Cylinder Inventory.</p>
                @endif
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Starting gas stock</label>
                    <input type="number" min="0" name="current_stock" value="{{ old('current_stock', 0) }}" required
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Max capacity</label>
                    <input type="number" min="1" name="max_capacity" value="{{ old('max_capacity', 50) }}" required
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Reorder level</label>
                    <input type="number" min="0" name="reorder_level" value="{{ old('reorder_level', 20) }}" required
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
            </div>

            <div class="pt-2 flex gap-3">
                <button type="submit" @disabled($cylinders->isEmpty()) class="bg-maroon-600 hover:bg-maroon-700 disabled:opacity-50 text-white text-sm font-medium px-5 py-2.5 rounded-lg shadow-sm">
                    Save Gas
                </button>
                <a href="{{ route('admin.inventory.index') }}" class="text-sm text-gray-500 hover:text-gray-700 px-2 py-2.5">Cancel</a>
            </div>
        </form>
    </div>

</x-app-layout>