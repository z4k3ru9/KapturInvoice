<?php

namespace App\Models;

use App\Enums\UnitOfMeasure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quotation_id', 'product_id', 'title', 'description',
    'quantity', 'unit', 'unit_cost', 'discount', 'discount_is_percentage', 'sort_order', 'line_total',
])]
class QuotationItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit' => UnitOfMeasure::class,
            'unit_cost' => 'decimal:4',
            'discount' => 'decimal:2',
            'discount_is_percentage' => 'boolean',
            'line_total' => 'decimal:2',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
