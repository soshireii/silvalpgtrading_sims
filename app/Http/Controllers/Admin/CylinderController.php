<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cylinder;
use Illuminate\Http\Request;

class CylinderController extends Controller
{
    public function index()
    {
        $cylinders = Cylinder::with('products.cylinder')
            ->withCount(['orderItems', 'inventoryLogs'])
            ->orderBy('name')
            ->get();

        return view('admin.cylinders.index', compact('cylinders'));
    }

    public function create()
    {
        $cylinder = new Cylinder;

        return view('admin.cylinders.form', compact('cylinder'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:cylinders,name',
            'price' => 'required|numeric|min:0',
        ]);

        Cylinder::create([...$validated, 'is_active' => true]);

        return redirect()->route('admin.cylinders.index')->with('success', 'Cylinder size added.');
    }

    public function edit(Cylinder $cylinder)
    {
        return view('admin.cylinders.form', compact('cylinder'));
    }

    public function update(Request $request, Cylinder $cylinder)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:cylinders,name,' . $cylinder->id,
            'price' => 'required|numeric|min:0',
        ]);
        $cylinder->update($validated);

        return redirect()->route('admin.cylinders.index')->with('success', 'Cylinder size and price updated.');
    }

    public function destroy(Cylinder $cylinder)
    {
        if ($cylinder->products()->exists() || $cylinder->orderItems()->exists() || $cylinder->inventoryLogs()->exists()) {
            return back()->with('error', 'This cylinder size is linked to gas products or history and cannot be deleted.');
        }

        $cylinder->delete();

        return redirect()->route('admin.cylinders.index')->with('success', 'Cylinder size deleted.');
    }

    public function logs(Cylinder $cylinder)
    {
        $logs = $cylinder->inventoryLogs()->with('creator', 'order')->latest()->paginate(20);
        $inventoryItemName = $cylinder->name . ' cylinder';
        $backRoute = 'admin.cylinders.index';

        return view('admin.inventory.logs', compact('logs', 'inventoryItemName', 'backRoute'));
    }
}
