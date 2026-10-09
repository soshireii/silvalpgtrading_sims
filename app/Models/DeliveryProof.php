<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProof extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'delivery_staff_id',
        'reviewed_by',
        'proof_image_path',
        'status',
        'remarks',
        'review_note',
        'completed_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryStaff()
    {
        return $this->belongsTo(User::class, 'delivery_staff_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
