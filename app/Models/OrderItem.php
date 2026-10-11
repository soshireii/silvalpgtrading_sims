<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_id', 'quantity', 'has_own_cylinder',
        'unit_gas_price', 'unit_cylinder_fee',
    ];

    protected function casts(): array
    {
        return [
            'has_own_cylinder' => 'boolean',
            'unit_gas_price' => 'decimal:2',
            'unit_cylinder_fee' => 'decimal:2',
        ];
    }

    /** (gas price + cylinder fee) x quantity — worked out, not stored. */
    public function getLineTotalAttribute(): string
    {
        $unit = (float) $this->unit_gas_price + (float) $this->unit_cylinder_fee;

        return number_format($unit * (int) $this->quantity, 2, '.', '');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** The cylinder size of this line, taken from its product. */
    public function cylinder()
    {
        return $this->hasOneThrough(Cylinder::class, Product::class, 'id', 'id', 'product_id', 'cylinder_id');
    }
}
