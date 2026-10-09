<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cylinder;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    public function index()
    {
        $products = Product::with('cylinder')->withCount('orderItems')->orderBy('name')->get();
        return view('admin.inventory.index', compact('products'));
    }

    public function create()
    {
        $cylinders = Cylinder::where('is_active', true)->orderBy('name')->get();

        return view('admin.inventory.create', compact('cylinders'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'gas_price' => 'required|numeric|min:0',
            'cylinder_id' => 'required|exists:cylinders,id',
            'current_stock' => 'required|integer|min:0',
            'max_capacity' => 'required|integer|min:1',
            'reorder_level' => 'required|integer|min:0',
        ]);

        if ($validated['current_stock'] > $validated['max_capacity']) {
            return back()->withErrors(['current_stock' => 'Starting stock cannot exceed maximum capacity.'])->withInput();
        }

        $product = DB::transaction(function () use ($validated, $request) {
            $product = Product::create([...$validated, 'is_active' => true]);

            if ($product->current_stock > 0) {
                InventoryLog::create([
                    'product_id' => $product->id,
                    'cylinder_id' => $product->cylinder_id,
                    'type' => 'adjustment',
                    'quantity_change' => $product->current_stock,
                    'stock_after' => $product->current_stock,
                    'note' => 'Opening gas stock',
                    'created_by' => $request->user()->id,
                ]);
            }

            return $product;
        });

        return redirect()->route('admin.inventory.index')->with('success', 'Gas product added.');
    }

    public function edit(Product $product)
    {
        $cylinders = Cylinder::where('is_active', true)->orderBy('name')->get();

        return view('admin.inventory.edit', compact('product', 'cylinders'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'gas_price' => 'required|numeric|min:0',
            'cylinder_id' => 'required|exists:cylinders,id',
            'current_stock' => 'required|integer|min:0',
            'max_capacity' => 'required|integer|min:1',
            'reorder_level' => 'required|integer|min:0',
        ]);

        if ($validated['current_stock'] > $validated['max_capacity']) {
            return back()->withErrors(['current_stock' => 'Current stock cannot exceed maximum capacity.'])->withInput();
        }

        $stockChange = $validated['current_stock'] - $product->current_stock;
        $product->update($validated);

        if ($stockChange !== 0) {
            InventoryLog::create([
                'product_id' => $product->id,
                'cylinder_id' => $validated['cylinder_id'],
                'type' => 'adjustment',
                'quantity_change' => $stockChange,
                'stock_after' => $validated['current_stock'],
                'note' => 'Gas stock changed while editing the gas product',
                'created_by' => $request->user()->id,
            ]);
        }

        return redirect()->route('admin.inventory.index')->with('success', 'Gas product updated.');
    }

    public function destroy(Product $product)
    {
        if ($product->orderItems()->exists() || $product->inventoryLogs()->exists()) {
            return back()->with('error', 'This gas product has inventory or order history and cannot be deleted. You can edit it instead.');
        }

        $product->delete();

        return redirect()->route('admin.inventory.index')->with('success', 'Gas product deleted.');
    }

    public function restock(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);
        $this->orderService->restock($product, $validated['quantity'], $request->user()->id, $validated['note'] ?? null);
        return back()->with('success', "Restocked {$product->name} by {$validated['quantity']} units.");
    }

    public function logs(Product $product)
    {
        $logs = $product->inventoryLogs()->with('creator', 'order')->latest()->paginate(20);
        $inventoryItemName = $product->name . ' gas';
        $backRoute = 'admin.inventory.index';
        return view('admin.inventory.logs', compact('logs', 'inventoryItemName', 'backRoute'));
    }
}
