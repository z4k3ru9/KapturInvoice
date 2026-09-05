<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tax_rate_id', 'name', 'rate', 'amount'])]
class InvoiceItemTax extends Model
{
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:3',
            'amount' => 'decimal:2',
        ];
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
