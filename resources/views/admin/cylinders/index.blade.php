<x-app-layout title="Cylinders" header="Cylinder Inventory">
    <div class="flex flex-wrap justify-between gap-3 mb-4">
        <div class="flex gap-3">
            <a href="{{ route('admin.inventory.index') }}" class="inline-flex items-center border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium px-4 py-2.5 rounded-lg">Manage gas</a>
            <a href="{{ route('admin.cylinders.create') }}" class="inline-flex items-center bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg">Add cylinder size</a>
        </div>
    </div>
    <p class="text-sm text-gray-500 mb-5">Create cylinder sizes and their purchase prices here. When adding gas stock, link it to a cylinder size. Gas-only exchanges charge no cylinder price; buying a cylinder adds the linked size's price.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        @forelse($cylinders as $cylinder)
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <h2 class="font-semibold text-gray-900">{{ $cylinder->name }}</h2>
                    <p class="text-xs text-gray-400">Cylinder purchase price ₱{{ number_format($cylinder->price, 2) }}</p>
                </div>
                <span class="text-xs text-gray-500">{{ $cylinder->products->count() }} gas product(s)</span>
            </div>
            @if($cylinder->products->isNotEmpty())
            <ul class="mt-3 space-y-1 text-sm text-gray-600">
                @foreach($cylinder->products as $product)
                <li class="flex justify-between gap-3">
                    <span>{{ $product->name }} ({{ $product->cylinder?->name }})</span>
                    <span>{{ $product->current_stock }} gas in stock</span>
                </li>
                @endforeach
            </ul>
            @else
            <p class="mt-3 text-xs text-gray-400">No gas products use this size yet.</p>
            @endif
            <div class="flex gap-3 mt-3 pt-3 border-t border-gray-100 text-xs">
                <a href="{{ route('admin.cylinders.edit', $cylinder) }}" class="text-maroon-600 hover:underline">Edit size / price</a>
                <a href="{{ route('admin.cylinders.logs', $cylinder) }}" class="text-maroon-600 hover:underline">Audit log →</a>
                @if($cylinder->products->isEmpty() && $cylinder->order_items_count === 0 && $cylinder->inventory_logs_count === 0)
                <form method="POST" action="{{ route('admin.cylinders.destroy', $cylinder) }}" onsubmit="return confirm('Delete this cylinder size?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-status-danger hover:underline">Delete</button>
                </form>
                @endif
            </div>
        </div>
        @empty
        <div class="bg-white rounded-xl border border-gray-200 p-6 text-sm text-gray-500">
            No cylinder sizes yet. Add a size here, then link gas products to it in Gas Inventory.
        </div>
        @endforelse
    </div>
</x-app-layout>
