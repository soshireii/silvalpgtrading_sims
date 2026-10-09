<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Cylinder;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    public function index()
    {
        $products = Product::with('cylinder')->orderBy('name')->get();
        $cylinders = Cylinder::with('products.cylinder')->orderBy('name')->get();
        return view('cashier.inventory.index', compact('products', 'cylinders'));
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
}
