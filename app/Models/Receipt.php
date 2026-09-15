<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'payment_id', 'number', 'issued_at', 'document_language', 'snapshot'])]
class Receipt extends Model
{
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            // Frozen at issuance by App\Actions\Receivables\
            // IssuePaymentReceipt, never touched again — the PDF view
            // renders from this, not the payment's live allocations,
            // so a later App\Actions\Receivables\AmendPaymentAllocation
            // can never silently change what this receipt shows.
            'snapshot' => 'array',
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

    /**
     * Printed-document language: same resolution as
     * `Invoice::resolveDocumentLanguage()` — this receipt's own override
     * when set, otherwise the owning company's `default_document_language`,
     * otherwise Bahasa Indonesia.
     */
    public function resolveDocumentLanguage(): string
    {
        return $this->document_language ?? $this->company->settings?->default_document_language ?? 'id';
    }
}
