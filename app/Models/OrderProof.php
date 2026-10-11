<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A photo proof attached to an order. DeliveryProof and GcashPaymentProof are
 * the two kinds (order_proofs.kind), each limited to its own rows.
 */
abstract class OrderProof extends Model
{
    use HasFactory;

    protected $table = 'order_proofs';

    protected $fillable = [
        'order_id',
        'uploaded_by',
        'reviewed_by',
        'image_path',
        'status',
        'remarks',
        'review_note',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    abstract public static function kind(): string;

    protected static function booted(): void
    {
        static::addGlobalScope('kind', fn ($query) => $query->where('order_proofs.kind', static::kind()));
        static::creating(fn (self $proof) => $proof->kind = static::kind());
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
