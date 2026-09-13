<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'payment_id', 'number', 'issued_at'])]
class Receipt extends Model
{
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** Never mutates this receipt's own snapshot — see ReceiptAmendment. */
    public function amendments(): HasMany
    {
        return $this->hasMany(ReceiptAmendment::class);
    }
}
