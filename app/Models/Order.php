<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /** SQL for an order's total, for reports (grand_total is not a column — see the orders migration). */
    public const GRAND_TOTAL_SQL = 'subtotal + cylinder_fee_total';

    protected $fillable = [
        'order_code',
        'customer_id',
        'order_date',
        'fulfillment_type',
        'scheduled_delivery_date',
        'delivery_address',
        'subtotal',
        'cylinder_fee_total',
        'payment_method',
        'payment_status',
        'paid_at',
        'status',
        'assigned_delivery_id',
        'delivered_at',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'scheduled_delivery_date' => 'date',
            'paid_at' => 'datetime',
            'delivered_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'cylinder_fee_total' => 'decimal:2',
        ];
    }

    /** Orders that count as sales (everything except cancelled). */
    public function scopeSales(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /** Free-text search over order code, customer name and customer phone. */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('order_code', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->search($term));
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveryStaff()
    {
        return $this->belongsTo(User::class, 'assigned_delivery_id');
    }

    public function deliveryProof()
    {
        return $this->hasOne(DeliveryProof::class);
    }

    public function gcashPaymentProof()
    {
        return $this->hasOne(GcashPaymentProof::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    // ---- values that used to be columns, now worked out (3NF) -------------------------

    public function getGrandTotalAttribute(): string
    {
        return number_format((float) $this->subtotal + (float) $this->cylinder_fee_total, 2, '.', '');
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->payment_status === 'paid_in_full';
    }

    public function getInvoiceNumberAttribute(): ?string
    {
        return $this->order_code ? 'INV-' . substr($this->order_code, 4) : null;
    }

    /** Pick-up orders have no address in the database; show "Store pickup" like before. */
    public function getDeliveryAddressAttribute(?string $value): ?string
    {
        return $value ?? ($this->fulfillment_type === 'pickup' ? 'Store pickup' : null);
    }

    public function getOrderedAtAttribute()
    {
        return $this->created_at;
    }

    /**
     * Status -> indicator color, per UI spec:
     * green = completed, yellow = pending/out_for_delivery (in progress / could be late), red = cancelled
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'green',
            'out_for_delivery' => 'yellow',
            'pending' => 'yellow',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => $this->fulfillment_type === 'pickup' ? 'Awaiting Pick-up' : 'Pending',
            'out_for_delivery' => 'Out for Delivery',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'not_paid' => 'Not Paid',
            'waiting_for_payment' => 'Waiting for Payment',
            'paid_in_full' => $this->payment_method === 'gcash' ? 'Paid' : 'Paid in Full',
            default => 'Not Paid',
        };
    }

    /** Flag orders sitting too long without being delivered (late indicator = yellow) */
    public function getIsDelayedAttribute(): bool
    {
        if (! in_array($this->status, ['pending', 'out_for_delivery'])) {
            return false;
        }

        $dueDate = $this->fulfillment_type === 'delivery'
            ? $this->scheduled_delivery_date
            : $this->order_date;

        return $dueDate?->lt(today()) ?? false;
    }
}
