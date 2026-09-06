<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A full HTML/CSS document (quote cover letter/SOW) — see
 * docs/filament-admin-layout-design.md §2.7. Kept separate from
 * Invoices/Quotes rather than bolted onto them; `App\Services\ProposalConverter`
 * turns an accepted one into a real Invoice (mirrors
 * App\Services\InvoiceDuplicator::convertQuoteToInvoice()), recorded on
 * `invoice_id` so "already converted" is a simple presence check.
 */
#[Fillable([
    'company_id', 'client_id', 'proposal_template_id', 'legacy_proposal_id',
    'title', 'html', 'css', 'status', 'amount', 'valid_until',
])]
class Proposal extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
            'amount' => 'decimal:2',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProposalTemplate::class, 'proposal_template_id');
    }

    /** The invoice generated once this proposal was converted, if any. */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
