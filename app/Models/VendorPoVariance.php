<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vendor_purchase_order_id', 'approved_by_user_id', 'reason', 'amount', 'approved_at'])]
class VendorPoVariance extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function vendorPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(VendorPurchaseOrder::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
