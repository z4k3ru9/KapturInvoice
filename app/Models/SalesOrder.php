<?php

namespace App\Models;

use App\Enums\JobType;
use App\Enums\SalesOrderStatus;
use App\Enums\ServiceReportResult;
use App\Enums\ServiceReportStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The "Job" aggregate (docs/rebuild/specs/03-sales-and-job/Specs.md) — a
 * NEW aggregate, never an extension of the frozen `Project`/`Task` models
 * (see docs/REFACTOR_PLAN.md §2 risk #3 and Project's own docblock). Only
 * ever created from an Accepted Quotation via
 * App\Actions\Sales\CreateSalesOrderFromQuotation, which also snapshots
 * `source_snapshot` and copies items into `sales_order_items` — the two
 * are never re-synced from the quotation afterward.
 */
#[Fillable([
    'company_id', 'client_id', 'quotation_id', 'number', 'status',
    'approved_value', 'source_snapshot', 'requires_handover', 'job_type', 'document_language',
])]
class SalesOrder extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => SalesOrderStatus::class,
            'approved_value' => 'decimal:2',
            'source_snapshot' => 'array',
            'requires_handover' => 'boolean',
            'job_type' => JobType::class,
            'approved_at' => 'datetime',
            'operational_closed_at' => 'datetime',
            'financial_closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class)->orderBy('sort_order');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(PaymentMilestone::class)->orderBy('sort_order');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(JobVariation::class)->latest('id');
    }

    /**
     * "Separate operational closure from financial closure" (Specs.md) —
     * the job is fully Closed only once both sides are independently
     * marked, not as a side effect of the `Closed` status transition
     * alone.
     */
    public function isFullyClosed(): bool
    {
        return $this->operational_closed_at !== null && $this->financial_closed_at !== null;
    }

    /** Phase 05 (docs/rebuild/specs/05-procurement-and-delivery). */
    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function handoverReports(): HasMany
    {
        return $this->hasMany(HandoverReport::class);
    }

    /** Phase 05 (FINALIZED-DECISIONS.md §10, Service Report). */
    public function serviceReports(): HasMany
    {
        return $this->hasMany(ServiceReport::class);
    }

    public function jobCostAllocations(): HasMany
    {
        return $this->hasMany(JobCostAllocation::class);
    }

    /** Invoices billed against this job (App\Models\Invoice::sales_order_id, Phase 04). */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * "Goods-only jobs may close operationally after delivery; installation
     * / service jobs require handover before operational closure." Checks
     * every `sales_order_items` row has had its full quantity covered by
     * `delivery_order_items` — the completeness gate
     * App\Actions\Delivery\CompleteHandover/App\Actions\Sales\
     * CloseJobOperationally consult before an Admin/Owner override.
     */
    public function isFullyDelivered(): bool
    {
        $delivered = DeliveryOrderItem::query()
            ->whereIn('sales_order_item_id', $this->items()->pluck('id'))
            ->selectRaw('sales_order_item_id, sum(quantity_delivered) as total')
            ->groupBy('sales_order_item_id')
            ->pluck('total', 'sales_order_item_id');

        foreach ($this->items as $item) {
            if ((float) ($delivered[$item->id] ?? 0) < (float) $item->quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * "Handover on a service job is available only when at least one
     * approved report is Resolved and no approved report remains
     * Follow-up required." — FINALIZED-DECISIONS.md §10. The service-job
     * counterpart to `isFullyDelivered()` — consulted by
     * App\Actions\Delivery\CompleteHandover only when `job_type` is
     * Service, before an Admin/Owner override.
     */
    public function isServiceReportsResolvedForHandover(): bool
    {
        $approved = $this->serviceReports()
            ->where('status', ServiceReportStatus::Approved)
            ->get();

        if ($approved->isEmpty()) {
            return false;
        }

        $hasResolved = $approved->contains(fn (ServiceReport $report) => $report->result === ServiceReportResult::Resolved);
        $hasOpenFollowUp = $approved->contains(fn (ServiceReport $report) => $report->result === ServiceReportResult::FollowUpRequired);

        return $hasResolved && ! $hasOpenFollowUp;
    }

    /**
     * Printed-document language: same resolution as
     * `Invoice::resolveDocumentLanguage()` — this job's own override when
     * set, otherwise the owning company's `default_document_language`,
     * otherwise Bahasa Indonesia.
     */
    public function resolveDocumentLanguage(): string
    {
        return $this->document_language ?? $this->company->settings?->default_document_language ?? 'id';
    }
}
