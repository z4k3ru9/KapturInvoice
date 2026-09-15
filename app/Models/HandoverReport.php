<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'sales_order_id', 'number', 'handover_date', 'notes',
    'is_override', 'override_reason', 'created_by_user_id',
    'document_language',
])]
class HandoverReport extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'handover_date' => 'date',
            'is_override' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Printed-document language: this handover report's own override when
     * set, otherwise the owning company's `default_document_language`,
     * otherwise Bahasa Indonesia — mirrors
     * App\Models\Invoice::resolveDocumentLanguage(). See
     * docs/rebuild/specs/06b-ux-browser-soa/Specs.md "Required launch
     * document coverage."
     */
    public function resolveDocumentLanguage(): string
    {
        return $this->document_language ?? $this->company->settings?->default_document_language ?? 'id';
    }
}
