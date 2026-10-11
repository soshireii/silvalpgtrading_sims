<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Cylinder;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;

/** Data for the shared inventory page (stock levels, stock in/out, supplier orders). */
trait BuildsInventoryPage
{
    protected function inventoryPageData(): array
    {
        return [
            'products' => Product::with('cylinder')->withCount('orderItems')->orderBy('name')->get(),
            'cylinders' => Cylinder::with('products')->orderBy('name')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'openPurchaseOrders' => PurchaseOrder::with('supplier', 'items.product')
                ->where('status', 'ordered')->orderBy('expected_date')->get(),
            'recentMovements' => StockMovement::with('product', 'creator')->latest()->take(8)->get(),
            'outReasons' => collect(StockMovement::OUT_REASONS)->reject(fn ($r) => $r === 'sale')
                ->mapWithKeys(fn ($r) => [$r => StockMovement::REASON_LABELS[$r]])->all(),
        ];
    }
}
