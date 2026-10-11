<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The ONLY place that changes products.current_stock.
 * Every change writes a stock_movements row, so the balance can always be explained.
 */
class StockService
{
    /** Put stock in (supplier delivery, opening stock, cancelled order, count correction). */
    public function in(Product $product, int $quantity, string $reason, ?int $userId, ?string $note = null, ?int $orderId = null, ?int $purchaseOrderId = null): StockMovement
    {
        $this->assertQuantity($quantity);

        if (! in_array($reason, StockMovement::IN_REASONS, true)) {
            throw ValidationException::withMessages(['reason' => 'That reason cannot be used for stock in.']);
        }

        return $this->move($product, $quantity, $reason, $userId, $note, $orderId, $purchaseOrderId);
    }

    /** Take stock out (sale, damaged, lost, returned to supplier, count correction). */
    public function out(Product $product, int $quantity, string $reason, ?int $userId, ?string $note = null, ?int $orderId = null): StockMovement
    {
        $this->assertQuantity($quantity);

        if (! in_array($reason, StockMovement::OUT_REASONS, true)) {
            throw ValidationException::withMessages(['reason' => 'That reason cannot be used for stock out.']);
        }

        return $this->move($product, -$quantity, $reason, $userId, $note, $orderId);
    }

    private function move(Product $product, int $change, string $reason, ?int $userId, ?string $note, ?int $orderId, ?int $purchaseOrderId = null): StockMovement
    {
        return DB::transaction(function () use ($product, $change, $reason, $userId, $note, $orderId, $purchaseOrderId) {
            // Lock the row so two people can't change the same product's stock at once.
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $newStock = $product->current_stock + $change;

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Not enough stock: {$product->name} only has {$product->current_stock} left.",
                ]);
            }

            // Putting a cancelled order back can never overfill; everything else must respect capacity.
            if ($change > 0 && $reason !== 'order_cancelled' && $newStock > $product->max_capacity) {
                throw ValidationException::withMessages([
                    'quantity' => "This would exceed {$product->name}'s maximum capacity of {$product->max_capacity} (currently {$product->current_stock}).",
                ]);
            }

            $product->forceFill(['current_stock' => $newStock])->save();

            return StockMovement::create([
                'product_id' => $product->id,
                'quantity_change' => $change,
                'reason' => $reason,
                'stock_after' => $newStock,
                'order_id' => $orderId,
                'purchase_order_id' => $purchaseOrderId,
                'note' => $note,
                'created_by' => $userId,
            ]);
        });
    }

    private function assertQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be at least 1.']);
        }
    }
}
