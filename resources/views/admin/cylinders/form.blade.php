<x-app-layout title="{{ $cylinder->exists ? 'Edit Cylinder Size' : 'Add Cylinder Size' }}" header="{{ $cylinder->exists ? 'Edit Cylinder Size' : 'Add Cylinder Size' }}">
    <div class="max-w-lg bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <form method="POST" action="{{ $cylinder->exists ? route('admin.cylinders.update', $cylinder) : route('admin.cylinders.store') }}" class="space-y-4">
            @csrf
            @if($cylinder->exists)
                @method('PUT')
            @endif

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Cylinder size / name</label>
                <input type="text" name="name" value="{{ old('name', $cylinder->name) }}" required maxlength="100" placeholder="e.g. 9kg"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                @error('name')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Cylinder price (₱)</label>
                <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $cylinder->price) }}" required
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                @error('price')<p class="text-xs text-status-danger mt-1">{{ $message }}</p>@enderror
            </div>
            <p class="text-xs text-gray-400">Gas products select a cylinder size from this list. The cylinder price is added only when the customer buys a cylinder.</p>
            @if($cylinder->products()->exists())
                <p class="text-xs text-gray-500">Used by: {{ $cylinder->products()->pluck('name')->join(', ') }}</p>
            @endif
            <div class="pt-2 flex gap-3">
                <button type="submit" class="bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg">{{ $cylinder->exists ? 'Save Cylinder Size' : 'Add Cylinder Size' }}</button>
                <a href="{{ route('admin.cylinders.index') }}" class="text-sm text-gray-500 px-2 py-2.5">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
