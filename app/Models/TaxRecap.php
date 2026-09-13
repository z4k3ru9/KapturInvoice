<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id', 'reporting_period', 'external_reference', 'manual_entry_status',
    'filing_date', 'notes', 'attachment_reference',
    'adjusted_at', 'adjusted_by_user_id', 'adjustment_reason',
])]
class TaxRecap extends Model
{
    protected function casts(): array
    {
        return [
            'filing_date' => 'date',
            'adjusted_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by_user_id');
    }
}
