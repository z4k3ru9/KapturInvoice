<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vendor_bill_item_id', 'sales_order_id', 'method', 'quantity', 'amount', 'created_by_user_id'])]
class JobCostAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    public function vendorBillItem(): BelongsTo
    {
        return $this->belongsTo(VendorBillItem::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
