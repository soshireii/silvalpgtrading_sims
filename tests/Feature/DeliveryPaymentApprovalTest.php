<?php

use App\Models\Cylinder;
use App\Models\DeliveryProof;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function deliveryPaymentTestUser(string $role): User
{
    static $userNumber = 0;
    $userNumber++;

    return User::create([
        'name' => ucfirst($role) . ' Tester',
        'email' => "{$role}-payment-tester-{$userNumber}@example.com",
        'password' => 'password',
        'role' => $role,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
}

function deliveryPaymentTestOrder(User $creator, string $paymentMethod = 'cash', string $fulfillmentType = 'delivery'): Order
{
    $cylinder = Cylinder::create([
        'name' => 'Delivery Test ' . uniqid(),
        'price' => 1200,
        'is_active' => true,
    ]);

    $product = Product::create([
        'name' => 'Delivery Gas ' . uniqid(),
        'cylinder_id' => $cylinder->id,
        'gas_price' => 1800,
        'current_stock' => 5,
        'max_capacity' => 10,
        'reorder_level' => 1,
        'is_active' => true,
    ]);

    return app(OrderService::class)->createOrder(
        ['name' => 'Delivery Customer', 'phone' => '09170000000', 'address' => $fulfillmentType === 'pickup' ? 'Store pickup' : 'Delivery Test Address'],
        [['product_id' => $product->id, 'quantity' => 1, 'has_own_cylinder' => true]],
        $fulfillmentType === 'pickup' ? 'Store pickup' : 'Delivery Test Address',
        $paymentMethod,
        $creator->id,
        fulfillmentType: $fulfillmentType,
        scheduledDeliveryDate: $fulfillmentType === 'delivery' ? today()->toDateString() : null,
    );
}

function deliveryPaymentTestProduct(int $stock = 10): Product
{
    $cylinder = Cylinder::create([
        'name' => 'Order Form Test ' . uniqid(),
        'price' => 1200,
        'is_active' => true,
    ]);

    return Product::create([
        'name' => 'Order Form Gas ' . uniqid(),
        'cylinder_id' => $cylinder->id,
        'gas_price' => 1800,
        'current_stock' => $stock,
        'max_capacity' => 20,
        'reorder_level' => 2,
        'is_active' => true,
    ]);
}

function submitDeliveryPaymentTestPhoto(Order $order, User $deliveryStaff, \Illuminate\Foundation\Testing\TestCase $test): void
{
    $test->actingAs($deliveryStaff)
        ->post(route('delivery.orders.complete', $order), [
            'proof_image' => UploadedFile::fake()->create('delivery-proof.jpg', 100, 'image/jpeg'),
            'remarks' => 'Delivered to customer',
        ])
        ->assertRedirect(route('delivery.orders.show', $order));
}

it('starts unpaid and waits for payment after a delivery assignment', function () {
    $creator = deliveryPaymentTestUser('admin');
    $deliveryStaff = deliveryPaymentTestUser('delivery');
    $order = deliveryPaymentTestOrder($creator);

    expect($order->payment_status)->toBe('not_paid')
        ->and($order->is_paid)->toBeFalse()
        ->and($order->payment_status_label)->toBe('Not Paid');

    app(OrderService::class)->assignDelivery($order, $deliveryStaff->id);
    $order->refresh();

    expect($order->status)->toBe('out_for_delivery')
        ->and($order->payment_status)->toBe('waiting_for_payment')
        ->and($order->is_paid)->toBeFalse()
        ->and($order->payment_status_label)->toBe('Waiting for Payment');
});

it('records a delivery schedule, order timestamp, and printable invoice when an order is placed', function () {
    $admin = deliveryPaymentTestUser('admin');
    $product = deliveryPaymentTestProduct();
    $scheduledDate = now()->addDays(2)->toDateString();

    $this->actingAs($admin)
        ->post(route('admin.orders.store'), [
            'customer_name' => 'Scheduled Customer',
            'customer_phone' => '09170000001',
            'fulfillment_type' => 'delivery',
            'delivery_address' => 'Scheduled Delivery Address',
            'scheduled_delivery_date' => $scheduledDate,
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'has_own_cylinder' => 'true',
            ]],
        ])
        ->assertRedirect();

    $order = Order::whereHas('customer', fn ($query) => $query->where('name', 'Scheduled Customer'))->firstOrFail();
    expect($order->fulfillment_type)->toBe('delivery')
        ->and($order->scheduled_delivery_date->toDateString())->toBe($scheduledDate)
        ->and($order->ordered_at)->not->toBeNull()
        ->and($order->invoice_number)->toStartWith('INV-');

    $this->get(route('admin.orders.invoice', $order))
        ->assertOk()
        ->assertSee($order->invoice_number)
        ->assertSee('Scheduled delivery')
        ->assertSee('Scheduled Customer')
        ->assertSee('Print Invoice');
});

it('creates pickup orders without a delivery address or delivery schedule and prevents delivery assignment', function () {
    $admin = deliveryPaymentTestUser('admin');
    $deliveryStaff = deliveryPaymentTestUser('delivery');
    $product = deliveryPaymentTestProduct();

    $this->actingAs($admin)
        ->post(route('admin.orders.store'), [
            'customer_name' => 'Pickup Customer',
            'customer_phone' => '09170000002',
            'fulfillment_type' => 'pickup',
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'has_own_cylinder' => 'true',
            ]],
        ])
        ->assertRedirect();

    $order = Order::whereHas('customer', fn ($query) => $query->where('name', 'Pickup Customer'))->firstOrFail();
    expect($order->fulfillment_type)->toBe('pickup')
        ->and($order->delivery_address)->toBe('Store pickup')
        ->and($order->scheduled_delivery_date)->toBeNull()
        ->and($order->status_label)->toBe('Awaiting Pick-up')
        ->and(fn () => app(OrderService::class)->assignDelivery($order, $deliveryStaff->id))
        ->toThrow(ValidationException::class);

    $this->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('Pick-up at store')
        ->assertDontSee('Assign Delivery')
        ->assertSee('Mark as Paid');

    $this->post(route('admin.orders.pickup-paid', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe('completed')
        ->and($order->payment_status)->toBe('paid_in_full')
        ->and($order->is_paid)->toBeTrue()
        ->and($order->paid_at)->not->toBeNull();

    $this->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertDontSee('Mark as Paid')
        ->assertSee('Pick-up completed and paid.');
});

it('disables and rejects delivery photo submission before the scheduled date', function () {
    Storage::fake('public');
    $admin = deliveryPaymentTestUser('admin');
    $deliveryStaff = deliveryPaymentTestUser('delivery');
    $order = deliveryPaymentTestOrder($admin);
    $order->update(['scheduled_delivery_date' => today()->addDay()]);
    app(OrderService::class)->assignDelivery($order, $deliveryStaff->id);

    $this->actingAs($deliveryStaff)
        ->get(route('delivery.orders.show', $order))
        ->assertOk()
        ->assertSee('x-ref="proofInput"', false)
        ->assertSee('Remove selected photo')
        ->assertSee('Available on Scheduled Date')
        ->assertSee('disabled', false)
        ->assertSee('Delivery completion is available on or after that date.');

    $this->post(route('delivery.orders.complete', $order), [
        'proof_image' => UploadedFile::fake()->create('early-proof.jpg', 100, 'image/jpeg'),
    ])->assertRedirect()
        ->assertSessionHas('error', 'This delivery cannot be marked delivered before its scheduled delivery date.');

    expect($order->fresh()->status)->toBe('out_for_delivery')
        ->and(DeliveryProof::where('order_id', $order->id)->exists())->toBeFalse();
});

it('completes a GCash pickup when the owner records its receipt', function () {
    Storage::fake('public');
    $admin = deliveryPaymentTestUser('admin');
    $order = deliveryPaymentTestOrder($admin, 'gcash', 'pickup');

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('Upload the GCash receipt above to mark this pick-up order paid and complete it.')
        ->assertDontSee('Mark as Paid');

    $this->post(route('admin.orders.gcash-receipt', $order), [
        'payment_receipt' => UploadedFile::fake()->create('pickup-gcash-receipt.jpg', 100, 'image/jpeg'),
    ])->assertRedirect()
        ->assertSessionHas('success', 'GCash receipt uploaded. The pick-up order is marked Paid and completed.');

    $order->refresh();
    expect($order->status)->toBe('completed')
        ->and($order->payment_status)->toBe('paid_in_full')
        ->and($order->is_paid)->toBeTrue()
        ->and($order->delivered_at)->not->toBeNull()
        ->and(Storage::disk('public')->exists($order->gcashPaymentProof->image_path))->toBeTrue();
});

it('requires an owner-uploaded GCash receipt before delivery assignment and keeps payment paid through delivery', function () {
    Storage::fake('public');
    $admin = deliveryPaymentTestUser('admin');
    $deliveryStaff = deliveryPaymentTestUser('delivery');
    $order = deliveryPaymentTestOrder($admin, 'gcash');

    expect($order->payment_status)->toBe('not_paid')
        ->and($order->payment_status_label)->toBe('Not Paid')
        ->and(fn () => app(OrderService::class)->assignDelivery($order, $deliveryStaff->id))
        ->toThrow(ValidationException::class);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('GCash Payment Receipt')
        ->assertSee('Upload Receipt &amp; Mark Paid', false)
        ->assertDontSee('Assign &amp; Mark Out for Delivery', false);

    $this->actingAs($admin)
        ->post(route('admin.orders.gcash-receipt', $order), [
            'payment_receipt' => UploadedFile::fake()->create('gcash-receipt.jpg', 100, 'image/jpeg'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $order->refresh();
    $paymentProof = $order->gcashPaymentProof()->firstOrFail();
    Storage::disk('public')->assertExists($paymentProof->image_path);
    expect($order->payment_status)->toBe('paid_in_full')
        ->and($order->payment_status_label)->toBe('Paid')
        ->and($order->is_paid)->toBeTrue()
        ->and($order->paid_at)->not->toBeNull();

    $this->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('GCash Payment Receipt')
        ->assertSee('Paid')
        ->assertSee('Assign &amp; Mark Out for Delivery', false);

    app(OrderService::class)->assignDelivery($order, $deliveryStaff->id);
    $order->refresh();
    expect($order->status)->toBe('out_for_delivery')
        ->and($order->payment_status)->toBe('paid_in_full')
        ->and($order->is_paid)->toBeTrue();

    submitDeliveryPaymentTestPhoto($order, $deliveryStaff, $this);
    $this->actingAs($admin)
        ->post(route('admin.orders.delivery-proof.review', $order), ['decision' => 'approve'])
        ->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe('completed')
        ->and($order->payment_status)->toBe('paid_in_full')
        ->and($order->payment_status_label)->toBe('Paid')
        ->and($order->is_paid)->toBeTrue();
});

it('keeps an order unpaid until an admin approves the delivery photo', function () {
    Storage::fake('public');
    $admin = deliveryPaymentTestUser('admin');
    $deliveryStaff = deliveryPaymentTestUser('delivery');
    $order = deliveryPaymentTestOrder($admin);
    app(OrderService::class)->assignDelivery($order, $deliveryStaff->id);

    submitDeliveryPaymentTestPhoto($order, $deliveryStaff, $this);

    $order->refresh();
    $proof = DeliveryProof::where('order_id', $order->id)->firstOrFail();
    Storage::disk('public')->assertExists($proof->proof_image_path);
    expect($proof->status)->toBe('pending')
        ->and($order->status)->toBe('out_for_delivery')
        ->and($order->payment_status)->toBe('waiting_for_payment')
        ->and($order->is_paid)->toBeFalse();

    $this->actingAs($admin)
        ->get(route('admin.orders.index', ['proof_status' => 'pending']))
        ->assertOk()
        ->assertSee('Delivery photo needs review');
    $this->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('Confirm Photo &amp; Mark Delivered', false)
        ->assertSee('Deny Photo')
        ->assertSee('Waiting for Payment');

    $this->post(route('admin.orders.delivery-proof.review', $order), [
        'decision' => 'approve',
    ])->assertRedirect();

    $order->refresh();
    $proof->refresh();
    expect($proof->status)->toBe('approved')
        ->and($proof->reviewed_by)->toBe($admin->id)
        ->and($proof->completed_at)->not->toBeNull()
        ->and($order->status)->toBe('completed')
        ->and($order->payment_status)->toBe('paid_in_full')
        ->and($order->is_paid)->toBeTrue()
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->delivered_at)->not->toBeNull();
});

it('lets delivery staff replace a denied photo without marking the order paid', function () {
    Storage::fake('public');
    $admin = deliveryPaymentTestUser('admin');
    $deliveryStaff = deliveryPaymentTestUser('delivery');
    $order = deliveryPaymentTestOrder($admin);
    app(OrderService::class)->assignDelivery($order, $deliveryStaff->id);
    submitDeliveryPaymentTestPhoto($order, $deliveryStaff, $this);

    $proof = DeliveryProof::where('order_id', $order->id)->firstOrFail();
    $oldPhoto = $proof->proof_image_path;

    $this->actingAs($admin)
        ->post(route('admin.orders.delivery-proof.review', $order), [
            'decision' => 'reject',
            'review_note' => 'Please include the signed receipt.',
        ])
        ->assertRedirect();

    $order->refresh();
    $proof->refresh();
    expect($proof->status)->toBe('rejected')
        ->and($proof->review_note)->toBe('Please include the signed receipt.')
        ->and($order->status)->toBe('out_for_delivery')
        ->and($order->payment_status)->toBe('waiting_for_payment')
        ->and($order->is_paid)->toBeFalse();

    submitDeliveryPaymentTestPhoto($order, $deliveryStaff, $this);

    $proof->refresh();
    Storage::disk('public')->assertMissing($oldPhoto);
    Storage::disk('public')->assertExists($proof->proof_image_path);
    expect($proof->status)->toBe('pending')
        ->and($proof->review_note)->toBeNull()
        ->and($order->fresh()->payment_status)->toBe('waiting_for_payment');
});

it('closes a pending photo review when the order is cancelled', function () {
    Storage::fake('public');
    $admin = deliveryPaymentTestUser('admin');
    $deliveryStaff = deliveryPaymentTestUser('delivery');
    $order = deliveryPaymentTestOrder($admin);
    app(OrderService::class)->assignDelivery($order, $deliveryStaff->id);
    submitDeliveryPaymentTestPhoto($order, $deliveryStaff, $this);

    app(OrderService::class)->cancelOrder($order, $admin->id, 'Customer cancelled');

    $order->refresh();
    $proof = DeliveryProof::where('order_id', $order->id)->firstOrFail();
    expect($proof->status)->toBe('cancelled')
        ->and($proof->review_note)->toBe('Order was cancelled before delivery photo review.')
        ->and($order->status)->toBe('cancelled')
        ->and($order->payment_status)->toBe('not_paid')
        ->and($order->is_paid)->toBeFalse();
});
