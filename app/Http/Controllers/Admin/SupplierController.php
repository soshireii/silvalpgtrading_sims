<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::withCount('purchaseOrders')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        Supplier::create([...$validated, 'is_active' => true]);

        return redirect()->route('admin.suppliers.index')->with('success', 'Supplier added.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate($this->rules($supplier) + ['is_active' => 'required|boolean']);

        $supplier->update($validated);

        return redirect()->route('admin.suppliers.index')->with('success', 'Supplier updated.');
    }

    /** A supplier that already has stock-in orders is kept for the history — deactivate it instead. */
    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', 'This supplier has stock-in orders and cannot be deleted. Edit it and set it to inactive instead.');
        }

        $supplier->delete();

        return redirect()->route('admin.suppliers.index')->with('success', 'Supplier deleted.');
    }

    private function rules(?Supplier $supplier = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($supplier?->id)],
            'contact_first_name' => 'nullable|string|max:100',
            'contact_last_name' => 'nullable|string|max:100',
            'phone' => ['nullable', 'regex:/^[0-9]{7,15}$/'],
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
        ];
    }
}
