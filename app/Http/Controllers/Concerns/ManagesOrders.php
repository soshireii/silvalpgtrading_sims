<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

/** Order-taking logic shared by the owner (Admin) and cashier controllers. */
trait ManagesOrders
{
    /**
     * The cylinder <select> in the form submits the words "true"/"false", but Laravel's
     * `boolean` rule only accepts true/false/1/0. Turn them into real booleans first.
     */
    protected function normalizeOrderItems(Request $request): void
    {
        $items = collect($request->input('items', []))
            ->map(function ($item) {
                $item['has_own_cylinder'] = filter_var($item['has_own_cylinder'] ?? true, FILTER_VALIDATE_BOOLEAN);

                return $item;
            })
            ->all();

        $request->merge(['items' => $items]);
    }

    protected function validateNewOrder(Request $request): array
    {
        $this->normalizeOrderItems($request);

        return $request->validate([
            'customer_first_name' => 'required|string|max:100',
            'customer_last_name' => 'required|string|max:100',
            'customer_phone' => ['required', 'regex:/^[0-9]{7,15}$/'],
            'fulfillment_type' => 'required|in:delivery,pickup',
            'delivery_address' => 'nullable|required_if:fulfillment_type,delivery|string|max:255',
            'scheduled_delivery_date' => 'nullable|required_if:fulfillment_type,delivery|date|after_or_equal:today',
            'payment_method' => 'required|in:cash,gcash',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.has_own_cylinder' => 'required|boolean',
        ]);
    }

    protected function createOrderFromRequest(Request $request, OrderService $orders): Order
    {
        $validated = $this->validateNewOrder($request);

        return $orders->createOrder(
            customerData: [
                'first_name' => $validated['customer_first_name'],
                'last_name' => $validated['customer_last_name'],
                'phone' => $validated['customer_phone'],
                'address' => $validated['delivery_address'] ?? null,
            ],
            items: $validated['items'],
            deliveryAddress: $validated['delivery_address'] ?? null,
            paymentMethod: $validated['payment_method'],
            createdBy: $request->user()->id,
            notes: $validated['notes'] ?? null,
            fulfillmentType: $validated['fulfillment_type'],
            scheduledDeliveryDate: $validated['scheduled_delivery_date'] ?? null,
        );
    }
}
