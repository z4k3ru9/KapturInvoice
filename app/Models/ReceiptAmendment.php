<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['receipt_id', 'reason', 'allocations_before', 'allocations_after', 'amended_by_user_id', 'amended_at'])]
class ReceiptAmendment extends Model
{
    protected function casts(): array
    {
        return [
            'allocations_before' => 'array',
            'allocations_after' => 'array',
            'amended_at' => 'datetime',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function amendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'amended_by_user_id');
    }
}
