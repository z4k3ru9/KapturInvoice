<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id', 'pricing_mode', 'subtotal', 'discount_total', 'taxable_base_total',
    'tax_total', 'pre_round_total', 'rounding_adjustment', 'total', 'items_snapshot', 'captured_at',
])]
class InvoiceTaxSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'taxable_base_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'pre_round_total' => 'decimal:2',
            'rounding_adjustment' => 'decimal:2',
            'total' => 'decimal:2',
            'items_snapshot' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
