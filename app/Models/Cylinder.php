<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cylinder extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /** Order lines of every gas product that uses this cylinder size. */
    public function orderItems()
    {
        return $this->hasManyThrough(OrderItem::class, Product::class);
    }

    /** Stock movements of every gas product that uses this cylinder size. */
    public function stockMovements()
    {
        return $this->hasManyThrough(StockMovement::class, Product::class);
    }
}
