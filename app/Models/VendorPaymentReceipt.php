<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One per verified VendorPayment — see App\Enums\VendorPaymentStatus. */
#[Fillable(['company_id', 'vendor_payment_id', 'number', 'issued_at', 'document_language', 'snapshot'])]
class VendorPaymentReceipt extends Model
{
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            // Frozen at issuance by App\Actions\Procurement\
            // IssueVendorPaymentReceipt, never touched again — the PDF
            // view renders from this, not the vendor payment's own
            // mutable `amount`, so a later App\Actions\Procurement\
            // AmendVendorPayment can never silently change what this
            // receipt shows.
            'snapshot' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vendorPayment(): BelongsTo
    {
        return $this->belongsTo(VendorPayment::class);
    }

    /** Never mutates this receipt's own snapshot — see VendorPaymentAmendment. */
    public function amendments(): HasMany
    {
        return $this->hasMany(VendorPaymentAmendment::class);
    }

    /**
     * Printed-document language: this receipt's own override when set,
     * otherwise the owning company's `default_document_language`, otherwise
     * Bahasa Indonesia — mirrors App\Models\Invoice::resolveDocumentLanguage().
     * See docs/rebuild/specs/06b-ux-browser-soa/Specs.md "Required launch
     * document coverage."
     */
    public function resolveDocumentLanguage(): string
    {
        return $this->document_language ?? $this->company->settings?->default_document_language ?? 'id';
    }
}
