<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'vendor_bill_id', 'vendor_purchase_order_item_id', 'product_id',
    'title', 'description', 'quantity', 'unit_cost',
    'net_amount', 'tax_amount', 'line_total', 'sort_order',
])]
class VendorBillItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'net_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class);
    }

    public function vendorPurchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(VendorPurchaseOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function jobCostAllocations(): HasMany
    {
        return $this->hasMany(JobCostAllocation::class);
    }

    /** The gross amount not yet allocated to any job — "show unallocated remainder." */
    public function unallocatedAmount(): float
    {
        return round((float) $this->line_total - (float) $this->jobCostAllocations()->sum('amount'), 2);
    }
}
