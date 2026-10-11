<?php

use App\Models\Cylinder;
use App\Models\Expense;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PurchaseOrderService;
use App\Services\StockService;
use Illuminate\Validation\ValidationException;

function inventoryTestUser(string $role = 'admin'): User
{
    return User::create([
        'first_name' => 'Inventory',
        'last_name' => 'Tester',
        'email' => $role . '-inventory-tester@example.com',
        'password' => 'password',
        'role' => $role,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
}

function inventoryTestCustomer(string $first = 'Test', string $last = 'Customer', string $phone = '09170000000'): array
{
    return ['first_name' => $first, 'last_name' => $last, 'phone' => $phone, 'address' => 'Test address'];
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

function inventoryTestSupplier(): Supplier
{
    return Supplier::create(['name' => 'Test Supplier', 'is_active' => true]);
}

it('charges only the gas price when the customer exchanges a cylinder', function () {
    [$product, $cylinder] = createInventoryTestProduct();
    $user = inventoryTestUser();

    $order = app(OrderService::class)->createOrder(
        inventoryTestCustomer(),
        inventoryTestItems($product, true, 2),
        'Test address',
        'cash',
        $user->id,
    );

    expect((float) $order->grand_total)->toBe(1900.0)
        ->and($product->fresh()->current_stock)->toBe(8)
        ->and($order->items->first()->cylinder?->id)->toBe($cylinder->id)
        ->and($order->items->first()->unit_cylinder_fee)->toBe('0.00')
        ->and(StockMovement::where('product_id', $product->id)->where('reason', 'sale')->value('quantity_change'))->toBe(-2)
        ->and($cylinder->stockMovements()->where('reason', 'sale')->value('quantity_change'))->toBe(-2);
});

it('adds the selected cylinder size price when the customer buys a cylinder', function () {
    [$product] = createInventoryTestProduct();
    $user = inventoryTestUser();

    $order = app(OrderService::class)->createOrder(
        inventoryTestCustomer(),
        inventoryTestItems($product, false, 2),
        'Test address',
        'cash',
        $user->id,
    );

    expect((float) $order->grand_total)->toBe(5500.0)
        ->and((float) $order->items->first()->line_total)->toBe(5500.0)
        ->and((float) $order->items->first()->unit_gas_price)->toBe(950.0)
        ->and((float) $order->items->first()->unit_cylinder_fee)->toBe(1800.0)
        ->and($product->fresh()->current_stock)->toBe(8);
});

it('reuses the same customer row for repeat orders', function () {
    [$product] = createInventoryTestProduct();
    $user = inventoryTestUser();
    $service = app(OrderService::class);

    $service->createOrder(inventoryTestCustomer(), inventoryTestItems($product, true), 'Test address', 'cash', $user->id);
    $service->createOrder(inventoryTestCustomer(), inventoryTestItems($product, true), 'Test address', 'cash', $user->id);

    expect(\App\Models\Customer::count())->toBe(1)
        ->and(\App\Models\Order::count())->toBe(2);
});

it('uses shared gas stock when checking both exchange and cylinder purchase orders', function () {
    [$product] = createInventoryTestProduct(gasStock: 1);
    $user = inventoryTestUser();

    expect(fn () => app(OrderService::class)->createOrder(
        inventoryTestCustomer(),
        inventoryTestItems($product, false, 2),
        'Test address',
        'cash',
        $user->id,
    ))->toThrow(ValidationException::class);

    expect($product->fresh()->current_stock)->toBe(1)
        ->and(StockMovement::count())->toBe(0);
});

it('counts duplicate gas lines together before reserving shared stock', function () {
    [$product] = createInventoryTestProduct(gasStock: 1);
    $user = inventoryTestUser();
    $items = [
        ...inventoryTestItems($product, false),
        ...inventoryTestItems($product, true),
    ];

    expect(fn () => app(OrderService::class)->createOrder(
        inventoryTestCustomer(),
        $items,
        'Test address',
        'cash',
        $user->id,
    ))->toThrow(ValidationException::class);

    expect($product->fresh()->current_stock)->toBe(1)
        ->and(StockMovement::count())->toBe(0);
});

it('restores stock when an order is cancelled, and only once', function () {
    [$product, $cylinder] = createInventoryTestProduct();
    $user = inventoryTestUser();
    $service = app(OrderService::class);
    $order = $service->createOrder(
        inventoryTestCustomer(),
        inventoryTestItems($product, false),
        'Test address',
        'cash',
        $user->id,
    );

    $service->cancelOrder($order, $user->id, 'Customer changed their mind');

    expect($product->fresh()->current_stock)->toBe(10)
        ->and(StockMovement::where('product_id', $product->id)->where('reason', 'order_cancelled')->value('quantity_change'))->toBe(1)
        ->and($cylinder->stockMovements()->where('reason', 'order_cancelled')->value('quantity_change'))->toBe(1);

    expect(fn () => $service->cancelOrder($order->fresh(), $user->id))->toThrow(ValidationException::class);
    expect($product->fresh()->current_stock)->toBe(10);
});

it('records every stock in and stock out in the ledger with the running balance', function () {
    [$product] = createInventoryTestProduct();
    $user = inventoryTestUser();
    $stock = app(StockService::class);

    $stock->in($product, 5, 'correction', $user->id, 'Recount found extra');
    $stock->out($product, 3, 'damaged', $user->id, 'Dropped cylinder');

    expect($product->fresh()->current_stock)->toBe(12);

    $movements = StockMovement::where('product_id', $product->id)->orderBy('id')->get();
    expect($movements->pluck('quantity_change')->all())->toBe([5, -3])
        ->and($movements->pluck('stock_after')->all())->toBe([15, 12])
        ->and($movements->pluck('type')->all())->toBe(['in', 'out']);
});

it('never lets stock go below zero or above capacity', function () {
    [$product] = createInventoryTestProduct(gasStock: 4);
    $user = inventoryTestUser();
    $stock = app(StockService::class);

    expect(fn () => $stock->out($product, 5, 'lost', $user->id))->toThrow(ValidationException::class);
    expect(fn () => $stock->in($product, 47, 'correction', $user->id))->toThrow(ValidationException::class);
    expect(fn () => $stock->out($product, 1, 'sale', $user->id))->not->toThrow(ValidationException::class);
    expect(fn () => $stock->in($product, 1, 'sale', $user->id))->toThrow(ValidationException::class);
    expect($product->fresh()->current_stock)->toBe(3);
});

it('adds stock only when a supplier delivery is checked in, and books the cost as an expense', function () {
    [$product] = createInventoryTestProduct();
    $user = inventoryTestUser();
    $supplier = inventoryTestSupplier();
    $service = app(PurchaseOrderService::class);

    $po = $service->create($supplier->id, [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 1200]], $user->id);

    expect($po->status)->toBe('ordered')
        ->and($product->fresh()->current_stock)->toBe(10)
        ->and(Expense::count())->toBe(0);

    $item = $po->items->first();
    $po = $service->receive($po, [$item->id => 8], $user->id);   // 2 short on the delivery

    expect($po->status)->toBe('received')
        ->and($product->fresh()->current_stock)->toBe(18)
        ->and(StockMovement::where('purchase_order_id', $po->id)->where('reason', 'supplier_delivery')->value('quantity_change'))->toBe(8)
        ->and((float) Expense::first()->amount)->toBe(9600.0)
        ->and(Expense::first()->category)->toBe('Supplier Restock')
        ->and((float) $po->total_cost)->toBe(9600.0);

    expect(fn () => $service->receive($po, [$item->id => 1], $user->id))->toThrow(ValidationException::class);
    expect($product->fresh()->current_stock)->toBe(18);
});

it('does not add stock for a cancelled supplier order', function () {
    [$product] = createInventoryTestProduct();
    $user = inventoryTestUser();
    $service = app(PurchaseOrderService::class);
    $po = $service->create(inventoryTestSupplier()->id, [['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 1000]], $user->id);

    $service->cancel($po);

    expect($po->fresh()->status)->toBe('cancelled')
        ->and($product->fresh()->current_stock)->toBe(10);
    expect(fn () => $service->receive($po->fresh(), [$po->items->first()->id => 5], $user->id))->toThrow(ValidationException::class);
});

it('lets the cashier order and check in stock from a supplier through the pages', function () {
    [$product] = createInventoryTestProduct();
    $cashier = inventoryTestUser('cashier');
    $supplier = inventoryTestSupplier();

    $this->actingAs($cashier)->get(route('cashier.inventory.index'))
        ->assertOk()->assertSee('Stock In from Supplier')->assertSee('Solane');

    $this->post(route('purchase-orders.store'), [
        'supplier_id' => $supplier->id,
        'items' => [['product_id' => $product->id, 'quantity' => 6, 'unit_cost' => 1100]],
        'receive_now' => '1',
    ])->assertRedirect();

    expect($product->fresh()->current_stock)->toBe(16)
        ->and(Expense::count())->toBe(1);

    $this->post(route('stock.out', $product), ['quantity' => 2, 'reason' => 'damaged'])->assertRedirect();
    expect($product->fresh()->current_stock)->toBe(14);

    $this->post(route('stock.out', $product), ['quantity' => 1, 'reason' => 'sale'])->assertSessionHasErrors('reason');
});

it('keeps suppliers owner-only', function () {
    $cashier = inventoryTestUser('cashier');
    $this->actingAs($cashier)->get(route('admin.suppliers.index'))->assertForbidden();

    $admin = inventoryTestUser('admin');
    $this->actingAs($admin)->get(route('admin.suppliers.index'))->assertOk();
    $this->post(route('admin.suppliers.store'), ['name' => 'New Dealer', 'contact_first_name' => 'Ana', 'contact_last_name' => 'Reyes'])
        ->assertRedirect(route('admin.suppliers.index'));

    expect(Supplier::where('name', 'New Dealer')->value('contact_last_name'))->toBe('Reyes');
});

it('renders cylinder sizes and gas products linked to them', function () {
    [$product, $cylinder] = createInventoryTestProduct();
    $user = inventoryTestUser();

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

    $this->get(route('admin.orders.create'))->assertRedirect(route('admin.orders.index', ['new' => 1]));

    $this->get(route('admin.orders.index', ['new' => 1]))
        ->assertOk()
        ->assertSee('Take New Order')
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
        ->and(StockMovement::where('product_id', $product->id)->value('quantity_change'))->toBe(6)
        ->and(StockMovement::where('product_id', $product->id)->value('reason'))->toBe('opening_stock');

    $exchangeOrder = app(OrderService::class)->createOrder(
        inventoryTestCustomer('Exchange', 'Customer'),
        inventoryTestItems($product, true),
        'Test address',
        'cash',
        $user->id,
    );
    $buyOrder = app(OrderService::class)->createOrder(
        inventoryTestCustomer('New', 'Customer', '09170000001'),
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
