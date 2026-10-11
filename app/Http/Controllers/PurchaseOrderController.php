<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;

/** Stock-in orders placed with suppliers (shared by owner and cashier). */
class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseOrderService $purchaseOrders) {}

    public function index(Request $request)
    {
        $query = PurchaseOrder::with('supplier', 'items.product');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $orders = $query->latest()->paginate(15)->withQueryString();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return view('purchase-orders.index', compact('orders', 'suppliers', 'products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'expected_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:500',
            'receive_now' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $po = $this->purchaseOrders->create(
            (int) $validated['supplier_id'],
            $validated['items'],
            $request->user()->id,
            $validated['expected_date'] ?? null,
            $validated['notes'] ?? null,
        );

        // "Already delivered": order and check it in as one step.
        if ($request->boolean('receive_now')) {
            $everything = $po->items->mapWithKeys(fn ($item) => [$item->id => $item->quantity_ordered])->all();
            $this->purchaseOrders->receive($po, $everything, $request->user()->id);

            return redirect()->route('purchase-orders.show', $po)
                ->with('success', "{$po->po_number} recorded and the stock was added to inventory.");
        }

        return redirect()->route('purchase-orders.show', $po)
            ->with('success', "{$po->po_number} created. Check it in when the supplier delivers.");
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('supplier', 'items.product', 'orderedBy', 'receivedBy', 'expense');

        return view('purchase-orders.show', ['order' => $purchaseOrder]);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'received' => 'required|array',
            'received.*' => 'required|integer|min:0',
        ]);

        $this->purchaseOrders->receive($purchaseOrder, $validated['received'], $request->user()->id);

        return back()->with('success', "{$purchaseOrder->po_number} received. Stock was added to inventory.");
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        $this->purchaseOrders->cancel($purchaseOrder);

        return back()->with('success', "{$purchaseOrder->po_number} cancelled.");
    }
}
