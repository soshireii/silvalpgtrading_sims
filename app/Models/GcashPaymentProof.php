<?php

namespace App\Models;

/** GCash receipt recorded by the owner/cashier. */
class GcashPaymentProof extends OrderProof
{
    public static function kind(): string
    {
        return 'payment';
    }

    /** When the receipt was (last) uploaded. */
    public function getUploadedAtAttribute()
    {
        return $this->updated_at;
    }
}
