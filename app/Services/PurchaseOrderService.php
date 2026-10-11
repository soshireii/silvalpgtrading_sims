<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stock-in from a supplier:  order it (status "ordered")  ->  check the delivery in ("received").
 * Receiving is what actually puts the gas into stock and books the cost as an expense.
 */
class PurchaseOrderService
{
    public const EXPENSE_CATEGORY = 'Supplier Restock';

    public function __construct(protected StockService $stock) {}

    /**
     * $items = [['product_id' => 1, 'quantity' => 10, 'unit_cost' => 1200], ...]
     */
    public function create(int $supplierId, array $items, int $userId, ?string $expectedDate = null, ?string $notes = null): PurchaseOrder
    {
        $productIds = array_column($items, 'product_id');
        if (count($productIds) !== count(array_unique($productIds))) {
            throw ValidationException::withMessages(['items' => 'Each gas product can only appear once on a stock-in order.']);
        }

        return DB::transaction(function () use ($supplierId, $items, $userId, $expectedDate, $notes) {
            $po = PurchaseOrder::create([
                'po_number' => $this->newNumber(),
                'supplier_id' => $supplierId,
                'status' => 'ordered',
                'order_date' => now()->toDateString(),
                'expected_date' => $expectedDate,
                'ordered_by' => $userId,
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'quantity_ordered' => (int) $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                ]);
            }

            return $po->load('items.product', 'supplier');
        });
    }

    /**
     * Check a delivery in. $received = [purchase_order_item_id => quantity actually delivered].
     * Items not listed are treated as "nothing delivered".
     */
    public function receive(PurchaseOrder $purchaseOrder, array $received, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $received, $userId) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);

            if ($po->status !== 'ordered') {
                throw ValidationException::withMessages(['status' => 'Only orders that are still waiting for delivery can be received.']);
            }

            $totalCost = 0;
            $anythingReceived = false;

            foreach ($po->items()->with('product')->get() as $item) {
                $qty = max(0, (int) ($received[$item->id] ?? 0));
                $item->update(['quantity_received' => $qty]);

                if ($qty > 0) {
                    $anythingReceived = true;
                    $totalCost += $qty * (float) $item->unit_cost;

                    $this->stock->in(
                        $item->product,
                        $qty,
                        'supplier_delivery',
                        $userId,
                        "Received on {$po->po_number}",
                        purchaseOrderId: $po->id,
                    );
                }
            }

            if (! $anythingReceived) {
                throw ValidationException::withMessages(['received' => 'Enter how many units arrived for at least one product, or cancel the order instead.']);
            }

            $po->update([
                'status' => 'received',
                'received_by' => $userId,
                'received_at' => now(),
            ]);

            if ($totalCost > 0) {
                $category = ExpenseCategory::firstOrCreate(['name' => self::EXPENSE_CATEGORY]);

                Expense::create([
                    'expense_category_id' => $category->id,
                    'amount' => $totalCost,
                    'expense_date' => now()->toDateString(),
                    'note' => "Stock-in {$po->po_number} from {$po->supplier->name}",
                    'purchase_order_id' => $po->id,
                    'created_by' => $userId,
                ]);
            }

            return $po->fresh(['items.product', 'supplier']);
        });
    }

    public function cancel(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);

            if ($po->status !== 'ordered') {
                throw ValidationException::withMessages(['status' => 'Only orders that are still waiting for delivery can be cancelled.']);
            }

            $po->update(['status' => 'cancelled']);

            return $po->fresh();
        });
    }

    private function newNumber(): string
    {
        do {
            $number = 'PO-' . strtoupper(Str::random(6));
        } while (PurchaseOrder::where('po_number', $number)->exists());

        return $number;
    }
}
