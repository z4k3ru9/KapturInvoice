<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A generated, read-only Statement of Account for one client within one
 * company over a reporting period — Phase 06B Slice 1
 * (docs/rebuild/specs/06b-ux-browser-soa/Specs.md). `snapshot` is the
 * full computed line data (opening balance, invoices, credits, receipts,
 * payments, closing balance, aging) frozen at generation time by
 * App\Actions\Reports\GenerateStatementOfAccount — "A generated or sent
 * SOA preserves an immutable PDF snapshot" — so the PDF view always
 * renders from this stored array rather than recomputing it live. A
 * *preview* (no `number`/`generated_at`, never persisted) calls
 * App\Services\Reports\BuildStatementOfAccount directly instead of going
 * through this model at all.
 */
#[Fillable([
    'company_id', 'client_id',
    'period_start', 'period_end',
    'opening_balance', 'closing_balance',
    'number', 'document_language',
    'generated_by_user_id', 'generated_at',
    'snapshot',
])]
class StatementOfAccount extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'generated_at' => 'datetime',
            'snapshot' => 'array',
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

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    /**
     * Printed-document language: same resolution as
     * `Invoice::resolveDocumentLanguage()` — this statement's own override
     * when set, otherwise the owning company's `default_document_language`,
     * otherwise Bahasa Indonesia.
     */
    public function resolveDocumentLanguage(): string
    {
        return $this->document_language ?? $this->company->settings?->default_document_language ?? 'id';
    }
}
