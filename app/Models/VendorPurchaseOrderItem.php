<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'vendor_purchase_order_id', 'product_id', 'title', 'description',
    'quantity', 'unit_cost', 'discount', 'discount_is_percentage', 'line_total', 'sort_order',
])]
class VendorPurchaseOrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'discount' => 'decimal:2',
            'discount_is_percentage' => 'boolean',
            'line_total' => 'decimal:2',
        ];
    }

    public function vendorPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(VendorPurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
