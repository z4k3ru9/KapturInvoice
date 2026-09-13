<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vendor_payment_receipt_id', 'reason', 'changes_before', 'changes_after', 'amended_by_user_id', 'amended_at'])]
class VendorPaymentAmendment extends Model
{
    protected function casts(): array
    {
        return [
            'changes_before' => 'array',
            'changes_after' => 'array',
            'amended_at' => 'datetime',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(VendorPaymentReceipt::class, 'vendor_payment_receipt_id');
    }

    public function amendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'amended_by_user_id');
    }
}
