<?php

namespace App\Models;

use App\Enums\TaxCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'invoice_id', 'legacy_invoice_item_id', 'product_id', 'title', 'description',
    'quantity', 'unit_cost', 'discount', 'discount_is_percentage', 'sort_order', 'line_total',
    'tax_category',
])]
class InvoiceItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'discount' => 'decimal:2',
            'discount_is_percentage' => 'boolean',
            'line_total' => 'decimal:2',
            'tax_category' => TaxCategory::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(InvoiceItemTax::class);
    }

    /**
     * The line's own tax_category if set, else its linked product's, else
     * StandardTaxable — see App\Services\Tax\TaxCalculationService.
     */
    public function resolveTaxCategory(): TaxCategory
    {
        return $this->tax_category ?? $this->product?->tax_category ?? TaxCategory::StandardTaxable;
    }
}
