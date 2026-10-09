<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\OrderController as AdminOrder;
use App\Http\Controllers\Admin\InventoryController as AdminInventory;
use App\Http\Controllers\Admin\CylinderController as AdminCylinder;
use App\Http\Controllers\Admin\ExpenseController as AdminExpense;
use App\Http\Controllers\Admin\ReportController as AdminReport;
use App\Http\Controllers\Admin\DeliveryStaffController as AdminDeliveryStaff;
use App\Http\Controllers\Cashier\DashboardController as CashierDashboard;
use App\Http\Controllers\Cashier\OrderController as CashierOrder;
use App\Http\Controllers\Cashier\InventoryController as CashierInventory;
use App\Http\Controllers\Cashier\ExpenseController as CashierExpense;
use App\Http\Controllers\Delivery\DashboardController as DeliveryDashboard;
use App\Http\Controllers\Delivery\OrderController as DeliveryOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    /** @var \App\Models\User|null $user */
    $user = Auth::user();

    return $user
        ? redirect()->route($user->homeRoute())
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    /** @var \App\Models\User $user */
    $user = Auth::user();

    return redirect()->route($user->homeRoute());
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

    Route::get('/orders', [AdminOrder::class, 'index'])->name('orders.index');
    Route::get('/orders/create', [AdminOrder::class, 'create'])->name('orders.create');
    Route::post('/orders', [AdminOrder::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [AdminOrder::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/invoice', [AdminOrder::class, 'invoice'])->name('orders.invoice');
    Route::post('/orders/{order}/assign', [AdminOrder::class, 'assign'])->name('orders.assign');
    Route::post('/orders/{order}/cancel', [AdminOrder::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/pickup-paid', [AdminOrder::class, 'markPickupAsPaid'])->name('orders.pickup-paid');
    Route::post('/orders/{order}/gcash-receipt', [AdminOrder::class, 'uploadGcashReceipt'])->name('orders.gcash-receipt');
    Route::post('/orders/{order}/delivery-proof/review', [AdminOrder::class, 'reviewDeliveryProof'])->name('orders.delivery-proof.review');

    Route::get('/inventory', [AdminInventory::class, 'index'])->name('inventory.index');
    Route::get('/inventory/create', [AdminInventory::class, 'create'])->name('inventory.create');
    Route::post('/inventory', [AdminInventory::class, 'store'])->name('inventory.store');
    Route::get('/inventory/{product}/edit', [AdminInventory::class, 'edit'])->name('inventory.edit');
    Route::put('/inventory/{product}', [AdminInventory::class, 'update'])->name('inventory.update');
    Route::delete('/inventory/{product}', [AdminInventory::class, 'destroy'])->name('inventory.destroy');
    Route::post('/inventory/{product}/restock', [AdminInventory::class, 'restock'])->name('inventory.restock');
    Route::get('/inventory/{product}/logs', [AdminInventory::class, 'logs'])->name('inventory.logs');

    Route::get('/cylinders', [AdminCylinder::class, 'index'])->name('cylinders.index');
    Route::get('/cylinders/create', [AdminCylinder::class, 'create'])->name('cylinders.create');
    Route::post('/cylinders', [AdminCylinder::class, 'store'])->name('cylinders.store');
    Route::get('/cylinders/{cylinder}/edit', [AdminCylinder::class, 'edit'])->name('cylinders.edit');
    Route::put('/cylinders/{cylinder}', [AdminCylinder::class, 'update'])->name('cylinders.update');
    Route::delete('/cylinders/{cylinder}', [AdminCylinder::class, 'destroy'])->name('cylinders.destroy');
    Route::get('/cylinders/{cylinder}/logs', [AdminCylinder::class, 'logs'])->name('cylinders.logs');

    Route::get('/expenses', [AdminExpense::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [AdminExpense::class, 'store'])->name('expenses.store');
    Route::post('/expenses/categories', [AdminExpense::class, 'storeCategory'])->name('expenses.categories.store');
    Route::delete('/expenses/{expense}', [AdminExpense::class, 'destroy'])->name('expenses.destroy');

    Route::get('/reports', [AdminReport::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [AdminReport::class, 'export'])->name('reports.export');

    // Delivery staff are non-permanent and not created via public registration —
    // the Owner is the only one who can create, edit, or remove their accounts.
    Route::get('/delivery-staff', [AdminDeliveryStaff::class, 'index'])->name('delivery-staff.index');
    Route::get('/delivery-staff/create', [AdminDeliveryStaff::class, 'create'])->name('delivery-staff.create');
    Route::post('/delivery-staff', [AdminDeliveryStaff::class, 'store'])->name('delivery-staff.store');
    Route::get('/delivery-staff/{deliveryStaff}/edit', [AdminDeliveryStaff::class, 'edit'])->name('delivery-staff.edit');
    Route::put('/delivery-staff/{deliveryStaff}', [AdminDeliveryStaff::class, 'update'])->name('delivery-staff.update');
    Route::delete('/delivery-staff/{deliveryStaff}', [AdminDeliveryStaff::class, 'destroy'])->name('delivery-staff.destroy');
});

Route::middleware(['auth', 'verified', 'role:cashier'])->prefix('cashier')->name('cashier.')->group(function () {
    Route::get('/dashboard', [CashierDashboard::class, 'index'])->name('dashboard');

    Route::get('/orders', [CashierOrder::class, 'index'])->name('orders.index');
    Route::get('/orders/create', [CashierOrder::class, 'create'])->name('orders.create');
    Route::post('/orders', [CashierOrder::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [CashierOrder::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/invoice', [CashierOrder::class, 'invoice'])->name('orders.invoice');
    Route::post('/orders/{order}/assign', [CashierOrder::class, 'assign'])->name('orders.assign');
    Route::post('/orders/{order}/cancel', [CashierOrder::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/pickup-paid', [CashierOrder::class, 'markPickupAsPaid'])->name('orders.pickup-paid');

    Route::get('/inventory', [CashierInventory::class, 'index'])->name('inventory.index');
    Route::post('/inventory/{product}/restock', [CashierInventory::class, 'restock'])->name('inventory.restock');
    Route::get('/expenses', [CashierExpense::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [CashierExpense::class, 'store'])->name('expenses.store');
});

Route::middleware(['auth', 'verified', 'role:delivery'])->prefix('delivery')->name('delivery.')->group(function () {
    Route::get('/dashboard', [DeliveryDashboard::class, 'index'])->name('dashboard');
    Route::get('/orders', [DeliveryOrder::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [DeliveryOrder::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/complete', [DeliveryOrder::class, 'complete'])->name('orders.complete');
});

require __DIR__ . '/auth.php';
