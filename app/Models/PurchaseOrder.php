<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A stock-in order placed with a supplier. */
class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'status',
        'order_date',
        'expected_date',
        'ordered_by',
        'received_by',
        'received_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'received_at' => 'datetime',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function orderedBy()
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function expense()
    {
        return $this->hasOne(Expense::class);
    }

    /** What this order costs: ordered quantities while open, what actually arrived once received. */
    public function getTotalCostAttribute(): string
    {
        $total = $this->items->sum(function (PurchaseOrderItem $item) {
            $qty = $this->status === 'received' ? (int) $item->quantity_received : $item->quantity_ordered;

            return $qty * (float) $item->unit_cost;
        });

        return number_format($total, 2, '.', '');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'ordered' => 'Waiting for delivery',
            'received' => 'Received',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'received' => 'green',
            'ordered' => 'yellow',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    /** Ordered but past its expected delivery date. */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'ordered' && $this->expected_date?->lt(today());
    }
}
