<?php

use App\Models\Cylinder;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;

function inventoryTestUser(): User
{
    return User::create([
        'name' => 'Inventory Tester',
        'email' => 'inventory-tester@example.com',
        'password' => 'password',
        'role' => 'admin',
        'is_active' => true,
    ]);
}

function inventoryTestItems(Product $product, bool $hasOwnCylinder, int $quantity = 1): array
{
    return [[
        'product_id' => $product->id,
        'quantity' => $quantity,
        'has_own_cylinder' => $hasOwnCylinder,
    ]];
}

function createInventoryTestProduct(int $gasStock = 10): array
{
    $cylinder = Cylinder::create([
        'name' => '11kg',
        'price' => 1800,
        'is_active' => true,
    ]);

    $product = Product::create([
        'name' => 'Solane',
        'cylinder_id' => $cylinder->id,
        'gas_price' => 950,
        'current_stock' => $gasStock,
        'max_capacity' => 50,
        'reorder_level' => 5,
        'is_active' => true,
    ]);

    return [$product, $cylinder];
}

it('charges only the gas price when the customer exchanges a cylinder', function () {
    [$product, $cylinder] = createInventoryTestProduct();
    $user = inventoryTestUser();

    $order = app(OrderService::class)->createOrder(
        ['name' => 'Customer', 'phone' => '09170000000', 'address' => 'Test address'],
        inventoryTestItems($product, true, 2),
        'Test address',
        'cash',
        $user->id,
    );

    expect((float) $order->grand_total)->toBe(1900.0)
        ->and($product->fresh()->current_stock)->toBe(8)
        ->and($order->items->first()->cylinder_id)->toBe($cylinder->id)
        ->and($order->items->first()->unit_cylinder_fee)->toBe('0.00')
        ->and(InventoryLog::where('product_id', $product->id)->where('type', 'sale')->value('quantity_change'))->toBe(-2)
        ->and(InventoryLog::where('cylinder_id', $cylinder->id)->where('type', 'sale')->value('quantity_change'))->toBe(-2);
});

it('adds the selected cylinder size price when the customer buys a cylinder', function () {
    [$product] = createInventoryTestProduct();
    $user = inventoryTestUser();

    $order = app(OrderService::class)->createOrder(
        ['name' => 'Customer', 'phone' => '09170000000', 'address' => 'Test address'],
        inventoryTestItems($product, false, 2),
        'Test address',
        'cash',
        $user->id,
    );

    expect((float) $order->grand_total)->toBe(5500.0)
        ->and((float) $order->items->first()->unit_gas_price)->toBe(950.0)
        ->and((float) $order->items->first()->unit_cylinder_fee)->toBe(1800.0)
        ->and($product->fresh()->current_stock)->toBe(8);
});

it('uses shared gas stock when checking both exchange and cylinder purchase orders', function () {
    [$product] = createInventoryTestProduct(gasStock: 1);
    $user = inventoryTestUser();

    expect(fn () => app(OrderService::class)->createOrder(
        ['name' => 'Customer', 'phone' => '09170000000', 'address' => 'Test address'],
        inventoryTestItems($product, false, 2),
        'Test address',
        'cash',
        $user->id,
    ))->toThrow(ValidationException::class);

    expect($product->fresh()->current_stock)->toBe(1)
        ->and(InventoryLog::count())->toBe(0);
});

it('restocks the shared gas and cylinder quantity only once', function () {
    [$product] = createInventoryTestProduct();
    $user = inventoryTestUser();

    app(OrderService::class)->restock($product, 3, $user->id, 'Supplier delivery');

    expect($product->fresh()->current_stock)->toBe(13)
        ->and(InventoryLog::where('product_id', $product->id)->where('type', 'restock')->count())->toBe(1)
        ->and(InventoryLog::where('cylinder_id', $product->cylinder_id)->where('type', 'restock')->count())->toBe(1);
});

it('counts duplicate gas lines together before reserving shared stock', function () {
    [$product] = createInventoryTestProduct(gasStock: 1);
    $user = inventoryTestUser();
    $items = [
        ...inventoryTestItems($product, false),
        ...inventoryTestItems($product, true),
    ];

    expect(fn () => app(OrderService::class)->createOrder(
        ['name' => 'Customer', 'phone' => '09170000000', 'address' => 'Test address'],
        $items,
        'Test address',
        'cash',
        $user->id,
    ))->toThrow(ValidationException::class);

    expect($product->fresh()->current_stock)->toBe(1)
        ->and(InventoryLog::count())->toBe(0);
});

it('restores shared stock and keeps cylinder audit entries when an order is cancelled', function () {
    [$product, $cylinder] = createInventoryTestProduct();
    $user = inventoryTestUser();
    $service = app(OrderService::class);
    $order = $service->createOrder(
        ['name' => 'Customer', 'phone' => '09170000000', 'address' => 'Test address'],
        inventoryTestItems($product, false),
        'Test address',
        'cash',
        $user->id,
    );

    $service->cancelOrder($order, $user->id, 'Customer changed their mind');

    expect($product->fresh()->current_stock)->toBe(10)
        ->and(InventoryLog::where('product_id', $product->id)->where('type', 'adjustment')->value('quantity_change'))->toBe(1)
        ->and(InventoryLog::where('cylinder_id', $cylinder->id)->where('type', 'adjustment')->value('quantity_change'))->toBe(1);
});

it('renders cylinder sizes and gas products linked to them', function () {
    [$product, $cylinder] = createInventoryTestProduct();
    $user = inventoryTestUser();
    $user->forceFill(['email_verified_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('admin.inventory.index'))
        ->assertOk()
        ->assertSee('Gas ₱950.00')
        ->assertSee('Manage cylinders');

    $this->get(route('admin.inventory.create'))
        ->assertOk()
        ->assertSee('Cylinder size')
        ->assertSee('11kg');

    $this->get(route('admin.cylinders.index'))
        ->assertOk()
        ->assertSee('11kg')
        ->assertSee('Solane')
        ->assertSee('1 gas product(s)')
        ->assertSee('Add cylinder size');

    $this->get(route('admin.cylinders.edit', $cylinder))
        ->assertOk()
        ->assertSee('name="name"', escape: false)
        ->assertDontSee('<select', escape: false);

    $this->get(route('admin.orders.create'))
        ->assertOk()
        ->assertSee('cylinder_name')
        ->assertSee('1800.00');
});

it('creates reusable cylinder sizes and links gas stock to the selected size', function () {
    $user = inventoryTestUser();
    $this->actingAs($user);

    $this->post(route('admin.cylinders.store'), [
        'name' => '9kg',
        'price' => 1200,
    ])->assertRedirect(route('admin.cylinders.index'));
    $cylinder = Cylinder::where('name', '9kg')->firstOrFail();

    $this->post(route('admin.inventory.store'), [
        'name' => 'Solane',
        'gas_price' => 1800,
        'cylinder_id' => $cylinder->id,
        'current_stock' => 6,
        'max_capacity' => 20,
        'reorder_level' => 4,
    ])->assertRedirect(route('admin.inventory.index'));

    $product = Product::where('name', 'Solane')->firstOrFail();
    expect($product->gas_price)->toBe('1800.00')
        ->and($product->cylinder_id)->toBe($cylinder->id)
        ->and($product->cylinder->name)->toBe('9kg')
        ->and($product->current_stock)->toBe(6)
        ->and(InventoryLog::where('product_id', $product->id)->value('quantity_change'))->toBe(6)
        ->and(InventoryLog::where('cylinder_id', $cylinder->id)->value('quantity_change'))->toBe(6);

    $exchangeOrder = app(OrderService::class)->createOrder(
        ['name' => 'Exchange Customer', 'phone' => '09170000000', 'address' => 'Test address'],
        inventoryTestItems($product, true),
        'Test address',
        'cash',
        $user->id,
    );
    $buyOrder = app(OrderService::class)->createOrder(
        ['name' => 'New Customer', 'phone' => '09170000001', 'address' => 'Test address'],
        inventoryTestItems($product, false),
        'Test address',
        'cash',
        $user->id,
    );

    expect((float) $exchangeOrder->grand_total)->toBe(1800.0)
        ->and((float) $buyOrder->grand_total)->toBe(3000.0)
        ->and($product->fresh()->current_stock)->toBe(4);

    $this->put(route('admin.cylinders.update', $cylinder), [
        'name' => '9kg Solane',
        'price' => 1250,
    ])->assertRedirect(route('admin.cylinders.index'));

    expect($product->fresh()->cylinder->name)->toBe('9kg Solane')
        ->and($product->fresh()->cylinder->price)->toBe('1250.00');
});
