<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['delivery_order_id', 'sales_order_item_id', 'description', 'quantity_delivered', 'sort_order'])]
class DeliveryOrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_delivered' => 'decimal:4',
        ];
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }
}
