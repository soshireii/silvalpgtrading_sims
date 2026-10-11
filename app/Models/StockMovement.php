<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of the stock ledger. quantity_change > 0 is stock IN, < 0 is stock OUT.
 */
class StockMovement extends Model
{
    use HasFactory;

    /** Reasons allowed for stock coming IN, and for stock going OUT. */
    public const IN_REASONS = ['opening_stock', 'supplier_delivery', 'order_cancelled', 'correction'];
    public const OUT_REASONS = ['sale', 'damaged', 'lost', 'returned_to_supplier', 'correction'];

    public const REASON_LABELS = [
        'opening_stock' => 'Opening stock',
        'supplier_delivery' => 'Supplier delivery',
        'order_cancelled' => 'Order cancelled',
        'sale' => 'Sale',
        'damaged' => 'Damaged',
        'lost' => 'Lost / missing',
        'returned_to_supplier' => 'Returned to supplier',
        'correction' => 'Count correction',
    ];

    protected $fillable = [
        'product_id',
        'quantity_change',
        'reason',
        'stock_after',
        'order_id',
        'purchase_order_id',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['quantity_change' => 'integer', 'stock_after' => 'integer'];
    }

    /** 'in' or 'out', worked out from the sign of quantity_change. */
    public function getTypeAttribute(): string
    {
        return $this->quantity_change >= 0 ? 'in' : 'out';
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === 'in' ? 'Stock In' : 'Stock Out';
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASON_LABELS[$this->reason] ?? ucfirst(str_replace('_', ' ', $this->reason));
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
