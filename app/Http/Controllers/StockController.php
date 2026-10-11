<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manual stock changes by the owner or cashier.
 * Normal stock-in comes from receiving a supplier order (PurchaseOrderController);
 * these are for count corrections and for stock leaving without a sale.
 */
class StockController extends Controller
{
    public function __construct(protected StockService $stock) {}

    /** Stock IN without a supplier order — e.g. a recount found more gas than the system says. */
    public function in(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        $this->stock->in($product, $validated['quantity'], 'correction', $request->user()->id, $validated['note'] ?? 'Count correction');

        return back()->with('success', "Added {$validated['quantity']} to {$product->name} stock.");
    }

    /** Stock OUT that is not a sale: damaged, lost, returned to the supplier, or a recount that found less. */
    public function out(Request $request, Product $product)
    {
        $reasons = array_values(array_diff(StockMovement::OUT_REASONS, ['sale']));

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'reason' => ['required', Rule::in($reasons)],
            'note' => 'nullable|string|max:255',
        ]);

        $this->stock->out($product, $validated['quantity'], $validated['reason'], $request->user()->id, $validated['note'] ?? null);

        return back()->with('success', "Removed {$validated['quantity']} from {$product->name} stock.");
    }
}
