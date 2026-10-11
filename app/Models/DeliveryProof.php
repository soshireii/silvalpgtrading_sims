<?php

namespace App\Models;

/** Delivery photo uploaded by delivery staff; the owner/cashier approves or rejects it. */
class DeliveryProof extends OrderProof
{
    public static function kind(): string
    {
        return 'delivery';
    }

    public function deliveryStaff()
    {
        return $this->uploader();
    }

    // ---- names the views already use -------------------------------------------------

    public function getProofImagePathAttribute(): ?string
    {
        return $this->image_path;
    }

    public function getDeliveryStaffIdAttribute(): ?int
    {
        return $this->uploaded_by;
    }

    /** Completed = approved; the time is when it was approved. */
    public function getCompletedAtAttribute()
    {
        return $this->status === 'approved' ? $this->reviewed_at : null;
    }
}
