<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'cylinder_id',
        'gas_price',
        'current_stock',
        'max_capacity',
        'reorder_level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gas_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function inventoryLogs()
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function cylinder()
    {
        return $this->belongsTo(Cylinder::class);
    }

    /**
     * Stock indicator used across dashboards:
     * red    = out of stock (0)
     * yellow = at/below reorder level (<= 20 by default) -> needs restock
     * green  = healthy stock
     */
    public function getStockStatusAttribute(): string
    {
        if ($this->current_stock <= 0) {
            return 'red';
        }
        if ($this->current_stock <= $this->reorder_level) {
            return 'yellow';
        }
        return 'green';
    }

    public function getStockLabelAttribute(): string
    {
        return match ($this->stock_status) {
            'red' => 'Out of Stock',
            'yellow' => 'Low Stock — Reorder',
            default => 'In Stock',
        };
    }

    public function getStockPercentAttribute(): int
    {
        if ($this->max_capacity <= 0) return 0;
        return (int) round(($this->current_stock / $this->max_capacity) * 100);
    }
}
