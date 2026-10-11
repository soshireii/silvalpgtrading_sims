<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Concerns\BuildsInventoryPage;
use App\Http\Controllers\Controller;
use App\Models\Product;

class InventoryController extends Controller
{
    use BuildsInventoryPage;

    /** Stock levels + Stock In / Stock Out / supplier orders (shared page with the owner). */
    public function index()
    {
        return view('inventory.index', $this->inventoryPageData() + ['prefix' => 'cashier', 'isAdmin' => false]);
    }

    public function logs(Product $product)
    {
        $logs = $product->stockMovements()->with('creator', 'order', 'purchaseOrder')->latest()->paginate(20);
        $inventoryItemName = $product->name . ' gas';
        $backRoute = 'cashier.inventory.index';

        return view('admin.inventory.logs', compact('logs', 'inventoryItemName', 'backRoute'));
    }
}
