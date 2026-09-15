<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id', 'number', 'reporting_period', 'external_reference', 'manual_entry_status',
    'filing_date', 'notes', 'attachment_reference', 'document_language',
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

    /**
     * Mirrors App\Models\Invoice::resolveDocumentLanguage() — a TaxRecap
     * has no direct Company relation (it belongs to an Invoice), so the
     * fallback reaches the company through it. Printed documents default
     * to Bahasa Indonesia with a per-document English override — see
     * docs/rebuild/specs/06b-ux-browser-soa/Specs.md.
     */
    public function resolveDocumentLanguage(): string
    {
        return $this->document_language ?? $this->invoice?->company->settings?->default_document_language ?? 'id';
    }
}
