<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'client_id', 'invoice_id', 'legacy_credit_id',
    'number', 'amount', 'credit_date', 'public_notes', 'private_notes',
    'document_language',
])]
class Credit extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'credit_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $credit) {
            // Balance starts equal to the credit's face amount and is drawn
            // down as it gets applied to invoices/payments — not directly
            // user-editable.
            $credit->balance ??= $credit->amount;
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Printed-document language: same resolution as
     * `Invoice::resolveDocumentLanguage()` — this credit's own override when
     * set, otherwise the owning company's `default_document_language`,
     * otherwise Bahasa Indonesia.
     */
    public function resolveDocumentLanguage(): string
    {
        return $this->document_language ?? $this->company->settings?->default_document_language ?? 'id';
    }
}
