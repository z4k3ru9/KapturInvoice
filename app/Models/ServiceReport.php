<?php

namespace App\Models;

use App\Enums\ServiceReportResult;
use App\Enums\ServiceReportStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One numbered visit report on a service-type job (`SVR`,
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §10, change request ratified
 * 2026-09-14) — a job may have several. Written only through
 * App\Actions\Delivery\{RecordServiceReport,SubmitServiceReport,
 * ApproveServiceReport,CancelServiceReport}, never a bare status edit.
 * `parts_used` is operational evidence only — it never creates an
 * invoice line or implies inventory; a billable scope change still
 * requires an approved App\Models\JobVariation. Approval freezes a
 * `snapshot` (mirrors App\Models\Receipt) so the printed document and the
 * service-job handover gate (App\Models\SalesOrder::
 * isServiceReportsResolvedForHandover()) both read a value that can never
 * silently change after approval.
 */
#[Fillable([
    'company_id', 'sales_order_id', 'number', 'status',
    'service_date', 'technician_user_id', 'external_technician_name',
    'problem_reported', 'diagnosis', 'action_taken', 'parts_used', 'result',
    'follow_up_notes', 'customer_acknowledgement_name',
    'created_by_user_id', 'document_language',
])]
class ServiceReport extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ServiceReportStatus::class,
            'result' => ServiceReportResult::class,
            'service_date' => 'date',
            'snapshot' => 'array',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Printed-document language: same resolution as
     * `Invoice::resolveDocumentLanguage()` — this report's own override
     * when set, otherwise the owning company's `default_document_language`,
     * otherwise Bahasa Indonesia.
     */
    public function resolveDocumentLanguage(): string
    {
        return $this->document_language ?? $this->company->settings?->default_document_language ?? 'id';
    }
}
