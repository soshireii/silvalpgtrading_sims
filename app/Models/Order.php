<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_code',
        'invoice_number',
        'customer_id',
        'order_date',
        'fulfillment_type',
        'scheduled_delivery_date',
        'ordered_at',
        'delivery_address',
        'subtotal',
        'cylinder_fee_total',
        'grand_total',
        'payment_method',
        'payment_status',
        'is_paid',
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
            'ordered_at' => 'datetime',
            'paid_at' => 'datetime',
            'delivered_at' => 'datetime',
            'is_paid' => 'boolean',
            'subtotal' => 'decimal:2',
            'cylinder_fee_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
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

    public function inventoryLogs()
    {
        return $this->hasMany(InventoryLog::class);
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
