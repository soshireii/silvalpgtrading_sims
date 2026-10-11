<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with('expenseCategory', 'creator');

        if ($from = $request->get('from')) {
            $query->whereDate('expense_date', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('expense_date', '<=', $to);
        }

        $expenses = $query->latest('expense_date')->paginate(15)->withQueryString();
        $total = (clone $query)->sum('amount');

        $categories = ExpenseCategory::orderBy('name')->get();

        return view('admin.expenses.index', compact('expenses', 'total', 'categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => ['required', 'string', Rule::exists('expense_categories', 'name')],
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);

        Expense::create([
            'expense_category_id' => ExpenseCategory::where('name', $validated['category'])->value('id'),
            'amount' => $validated['amount'],
            'expense_date' => $validated['expense_date'],
            'note' => $validated['note'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Expense recorded.');
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'category_name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')],
        ]);

        ExpenseCategory::create(['name' => trim($validated['category_name'])]);

        return back()->with('success', 'Expense category added.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();
        return back()->with('success', 'Expense deleted.');
    }
    public function updateCategory(Request $request, ExpenseCategory $expenseCategory)
    {
        $validated = $request->validateWithBag('editCategory', [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('expense_categories', 'name')->ignore($expenseCategory->id),
            ],
        ]);

        $expenseCategory->update(['name' => trim($validated['name'])]);

        return back()->with('success', 'Expense category updated.');
    }

    public function destroyCategory(ExpenseCategory $expenseCategory)
    {
        $inUse = Expense::where('expense_category_id', $expenseCategory->id)->exists();

        if ($inUse) {
            return back()->withErrors([
                'name' => "\"{$expenseCategory->name}\" has recorded expenses and can't be deleted. Rename it instead.",
            ], 'editCategory');
        }

        $expenseCategory->delete();

        return back()->with('success', 'Expense category deleted.');
    }
}
