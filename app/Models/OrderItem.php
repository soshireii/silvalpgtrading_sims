<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_id', 'cylinder_id', 'quantity', 'has_own_cylinder',
        'unit_gas_price', 'unit_cylinder_fee', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'has_own_cylinder' => 'boolean',
            'unit_gas_price' => 'decimal:2',
            'unit_cylinder_fee' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function cylinder()
    {
        return $this->belongsTo(Cylinder::class);
    }
}