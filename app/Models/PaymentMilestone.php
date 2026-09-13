<?php

namespace App\Models;

use App\Enums\MilestoneType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sales_order_id', 'type', 'description', 'percentage', 'is_percentage', 'amount', 'due_date', 'sort_order',
])]
class PaymentMilestone extends Model
{
    protected function casts(): array
    {
        return [
            'type' => MilestoneType::class,
            'percentage' => 'decimal:2',
            'is_percentage' => 'boolean',
            'amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }
}
