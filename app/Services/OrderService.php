<?php

namespace App\Services;

use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Cylinder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Create an order with line items, enforcing the business's pricing & stock rules:
     *  - Customer WITH an empty cylinder to exchange -> pays gas price only.
     *  - Customer WITHOUT a cylinder -> pays gas price + the linked cylinder price.
     *  - Payment method is cash or gcash ONLY, paid in full — no partial/down payment ever.
     *  - Stock is deducted immediately since an order = a confirmed sale in this business.
     */
    public function createOrder(
        array $customerData,
        array $items,
        string $deliveryAddress,
        string $paymentMethod,
        int $createdBy,
        ?string $notes = null,
        string $fulfillmentType = 'delivery',
        ?string $scheduledDeliveryDate = null
    ): Order
    {
        if (! in_array($paymentMethod, ['cash', 'gcash'], true)) {
            throw ValidationException::withMessages(['payment_method' => 'Only Cash or GCash payment is accepted.']);
        }

        if (! in_array($fulfillmentType, ['delivery', 'pickup'], true)) {
            throw ValidationException::withMessages(['fulfillment_type' => 'Choose delivery or pickup.']);
        }

        return DB::transaction(function () use ($customerData, $items, $deliveryAddress, $paymentMethod, $createdBy, $notes, $fulfillmentType, $scheduledDeliveryDate) {
            $customer = Customer::create($customerData);

            $subtotal = 0;
            $cylinderFeeTotal = 0;
            $lineItems = [];
            $reservedGas = [];

            foreach ($items as $item) {
                /** @var Product $product */
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                $qty = (int) $item['quantity'];
                $hasOwnCylinder = (bool) ($item['has_own_cylinder'] ?? true);

                if ($qty < 1) {
                    throw ValidationException::withMessages(['quantity' => 'Quantity must be at least 1.']);
                }

                $gasQuantity = ($reservedGas[$product->id] ?? 0) + $qty;
                if ($product->current_stock < $gasQuantity) {
                    throw ValidationException::withMessages([
                        'stock' => "Insufficient stock for {$product->name}. Available: {$product->current_stock}.",
                    ]);
                }
                $reservedGas[$product->id] = $gasQuantity;

                $cylinder = Cylinder::query()
                    ->where('id', $product->cylinder_id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $hasOwnCylinder && ! $cylinder) {
                    throw ValidationException::withMessages([
                        'cylinder' => "No active cylinder is configured for {$product->name}.",
                    ]);
                }

                $unitCylinderFee = $hasOwnCylinder ? 0 : $cylinder->price;
                $lineTotal = ($product->gas_price + $unitCylinderFee) * $qty;

                $subtotal += $product->gas_price * $qty;
                $cylinderFeeTotal += $unitCylinderFee * $qty;

                $lineItems[] = [
                    'product' => $product,
                    'cylinder' => $cylinder,
                    'quantity' => $qty,
                    'has_own_cylinder' => $hasOwnCylinder,
                    'unit_gas_price' => $product->gas_price,
                    'unit_cylinder_fee' => $unitCylinderFee,
                    'line_total' => $lineTotal,
                ];
            }

            $orderCode = 'ORD-' . strtoupper(Str::random(6));
            $order = Order::create([
                'order_code' => $orderCode,
                'invoice_number' => 'INV-' . substr($orderCode, 4),
                'customer_id' => $customer->id,
                'order_date' => now()->toDateString(),
                'fulfillment_type' => $fulfillmentType,
                'scheduled_delivery_date' => $fulfillmentType === 'delivery' ? $scheduledDeliveryDate : null,
                'ordered_at' => now(),
                'delivery_address' => $fulfillmentType === 'pickup' ? 'Store pickup' : $deliveryAddress,
                'subtotal' => $subtotal,
                'cylinder_fee_total' => $cylinderFeeTotal,
                'grand_total' => $subtotal + $cylinderFeeTotal,
                'payment_method' => $paymentMethod,
                'payment_status' => 'not_paid',
                'is_paid' => false,
                'paid_at' => null,
                'status' => 'pending',
                'created_by' => $createdBy,
                'notes' => $notes,
            ]);

            foreach ($lineItems as $li) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $li['product']->id,
                    'cylinder_id' => $li['cylinder']?->id,
                    'quantity' => $li['quantity'],
                    'has_own_cylinder' => $li['has_own_cylinder'],
                    'unit_gas_price' => $li['unit_gas_price'],
                    'unit_cylinder_fee' => $li['unit_cylinder_fee'],
                    'line_total' => $li['line_total'],
                ]);

                $product = $li['product'];
                $product->decrement('current_stock', $li['quantity']);

                InventoryLog::create([
                    'product_id' => $product->id,
                    'cylinder_id' => $li['cylinder']?->id,
                    'type' => 'sale',
                    'quantity_change' => -$li['quantity'],
                    'stock_after' => $product->fresh()->current_stock,
                    'order_id' => $order->id,
                    'note' => "Sold via order {$order->order_code}",
                    'created_by' => $createdBy,
                ]);

            }

            return $order->fresh(['items.product', 'items.cylinder', 'customer']);
        });
    }

    /** Record gas stock received from a supplier. */
    public function restock(Product $product, int $quantity, int $createdBy, ?string $note = null): Product
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Restock quantity must be at least 1.']);
        }

        return DB::transaction(function () use ($product, $quantity, $createdBy, $note) {
            $product = Product::lockForUpdate()->findOrFail($product->id);

            if ($product->current_stock + $quantity > $product->max_capacity) {
                throw ValidationException::withMessages([
                    'quantity' => "This restock exceeds {$product->name}'s maximum capacity of {$product->max_capacity}.",
                ]);
            }

            $product->increment('current_stock', $quantity);
            $newStock = $product->fresh()->current_stock;

            InventoryLog::create([
                'product_id' => $product->id,
                'cylinder_id' => $product->cylinder_id,
                'type' => 'restock',
                'quantity_change' => $quantity,
                'stock_after' => $newStock,
                'note' => $note ?? 'Stock received from supplier',
                'created_by' => $createdBy,
            ]);

            return $product->fresh();
        });
    }

    /** Assign an order after any required GCash payment has been recorded. */
    public function assignDelivery(Order $order, int $deliveryStaffId): Order
    {
        return DB::transaction(function () use ($order, $deliveryStaffId) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== 'pending') {
                throw ValidationException::withMessages([
                    'delivery_staff_id' => 'Only pending orders can be assigned for delivery.',
                ]);
            }

            if ($order->fulfillment_type !== 'delivery') {
                throw ValidationException::withMessages([
                    'delivery_staff_id' => 'Pickup orders cannot be assigned to delivery staff.',
                ]);
            }

            if ($order->payment_method === 'gcash' && $order->payment_status !== 'paid_in_full') {
                throw ValidationException::withMessages([
                    'delivery_staff_id' => 'GCash payment must be confirmed before this order can be assigned for delivery.',
                ]);
            }

            $order->update([
                'assigned_delivery_id' => $deliveryStaffId,
                'status' => 'out_for_delivery',
                'payment_status' => $order->payment_method === 'cash' ? 'waiting_for_payment' : 'paid_in_full',
                'is_paid' => $order->payment_method === 'gcash',
                'paid_at' => $order->payment_method === 'gcash' ? $order->paid_at : null,
            ]);

            return $order->fresh();
        });
    }

    /** Mark a pending cash pickup as paid and completed. */
    public function markPickupAsPaid(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->fulfillment_type !== 'pickup' || $order->status !== 'pending') {
                throw ValidationException::withMessages([
                    'payment_status' => 'Only pending pick-up orders can be marked paid here.',
                ]);
            }

            if ($order->payment_method === 'gcash') {
                throw ValidationException::withMessages([
                    'payment_status' => 'GCash pick-up orders must be marked paid by recording the GCash receipt.',
                ]);
            }

            if ($order->payment_status === 'paid_in_full') {
                throw ValidationException::withMessages([
                    'payment_status' => 'This pick-up order is already paid.',
                ]);
            }

            $paidAt = now();
            $order->update([
                'payment_status' => 'paid_in_full',
                'is_paid' => true,
                'paid_at' => $paidAt,
                'status' => 'completed',
                'delivered_at' => $paidAt,
            ]);

            return $order->fresh();
        });
    }

    /** Cancel an order and restock the reserved items back to inventory. */
    public function cancelOrder(Order $order, int $cancelledBy, ?string $reason = null): Order
    {
        if ($order->status === 'completed') {
            throw ValidationException::withMessages(['status' => 'A completed order cannot be cancelled.']);
        }

        return DB::transaction(function () use ($order, $cancelledBy, $reason) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            foreach ($order->items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item->product_id);
                $newStock = min($product->current_stock + $item->quantity, $product->max_capacity);
                $actualAdded = $newStock - $product->current_stock;
                $product->update(['current_stock' => $newStock]);

                InventoryLog::create([
                    'product_id' => $product->id,
                    'cylinder_id' => $item->cylinder_id,
                    'type' => 'adjustment',
                    'quantity_change' => $actualAdded,
                    'stock_after' => $newStock,
                    'order_id' => $order->id,
                    'note' => 'Restocked due to order cancellation: ' . ($reason ?? 'no reason given'),
                    'created_by' => $cancelledBy,
                ]);

            }

            $order->deliveryProof()
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    'review_note' => 'Order was cancelled before delivery photo review.',
                ]);

            $order->update([
                'status' => 'cancelled',
                'payment_status' => $order->is_paid ? 'paid_in_full' : 'not_paid',
                'notes' => trim(($order->notes ?? '') . ' | Cancelled: ' . $reason),
            ]);

            return $order->fresh();
        });
    }
}
