<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('stores expense ids and displays them in the expense list', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $expense = Expense::create([
        'category' => 'Fuel',
        'amount' => 125.50,
        'expense_date' => now()->toDateString(),
        'created_by' => $admin->id,
    ]);

    expect(Schema::hasColumn('expenses', 'id'))->toBeTrue()
        ->and($expense->id)->toBeInt();

    $this->actingAs($admin)
        ->get(route('admin.expenses.index'))
        ->assertOk()
        ->assertSee('EXP-'.str_pad((string) $expense->id, 6, '0', STR_PAD_LEFT));
});

it('provides the standard expense categories to cashiers', function () {
    $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

    $this->actingAs($cashier)
        ->get(route('cashier.expenses.index'))
        ->assertOk()
        ->assertSee('Fuel')
        ->assertSee('Supplier Restock')
        ->assertSee('Salaries')
        ->assertSee('Utilities')
        ->assertDontSee('Add Category');
});

it('allows only the owner to add expense categories', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('admin.expenses.categories.store'), ['category_name' => 'Vehicle Repairs'])
        ->assertRedirect();

    expect(ExpenseCategory::where('name', 'Vehicle Repairs')->exists())->toBeTrue();

    $this->actingAs($cashier)
        ->post(route('admin.expenses.categories.store'), ['category_name' => 'Unauthorized'])
        ->assertForbidden();
});

it('accepts only saved categories when recording an expense', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('admin.expenses.store'), [
            'category' => 'Fuel',
            'amount' => 250,
            'expense_date' => now()->toDateString(),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('expenses', [
        'category' => 'Fuel',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.expenses.store'), [
            'category' => 'Unlisted Category',
            'amount' => 250,
            'expense_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('category');
});