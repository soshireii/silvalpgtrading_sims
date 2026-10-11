<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsInventoryPage;
use App\Http\Controllers\Controller;
use App\Models\Cylinder;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    use BuildsInventoryPage;

    public function __construct(protected StockService $stock) {}

    /** Stock levels + Stock In / Stock Out / supplier orders (shared page with the cashier). */
    public function index()
    {
        return view('inventory.index', $this->inventoryPageData() + ['prefix' => 'admin', 'isAdmin' => true]);
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

        DB::transaction(function () use ($validated, $request) {
            $openingStock = $validated['current_stock'];
            $product = Product::create([...$validated, 'current_stock' => 0, 'is_active' => true]);

            if ($openingStock > 0) {
                $this->stock->in($product, $openingStock, 'opening_stock', $request->user()->id, 'Opening gas stock');
            }
        });

        return redirect()->route('admin.inventory.index')->with('success', 'Gas product added.');
    }

    public function edit(Product $product)
    {
        $cylinders = Cylinder::where('is_active', true)->orderBy('name')->get();

        return view('admin.inventory.edit', compact('product', 'cylinders'));
    }

    /** Stock is NOT edited here — use Stock In / Stock Out so every change is recorded. */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'gas_price' => 'required|numeric|min:0',
            'cylinder_id' => 'required|exists:cylinders,id',
            'max_capacity' => 'required|integer|min:1',
            'reorder_level' => 'required|integer|min:0',
        ]);

        if ($validated['max_capacity'] < $product->current_stock) {
            return back()->withErrors(['max_capacity' => "Maximum capacity cannot be lower than the current stock ({$product->current_stock})."])->withInput();
        }

        $product->update($validated);

        return redirect()->route('admin.inventory.index')->with('success', 'Gas product updated.');
    }

    public function destroy(Product $product)
    {
        if ($product->orderItems()->exists() || $product->stockMovements()->exists() || $product->purchaseOrderItems()->exists()) {
            return back()->with('error', 'This gas product has stock, order or supplier history and cannot be deleted. You can edit it instead.');
        }

        $product->delete();

        return redirect()->route('admin.inventory.index')->with('success', 'Gas product deleted.');
    }

    public function logs(Product $product)
    {
        $logs = $product->stockMovements()->with('creator', 'order', 'purchaseOrder')->latest()->paginate(20);
        $inventoryItemName = $product->name . ' gas';
        $backRoute = 'admin.inventory.index';

        return view('admin.inventory.logs', compact('logs', 'inventoryItemName', 'backRoute'));
    }
}
