<?php

namespace App\Actions\Delivery;

use App\Enums\CompanyRole;
use App\Enums\JobType;
use App\Models\HandoverReport;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentNumberGenerator;
use RuntimeException;

/**
 * "Handover Report is required only for installation/service jobs and may
 * require completed delivery. Multiple partial Delivery Orders are
 * allowed; Admin/Owner overrides require a reason." —
 * docs/rebuild/specs/05-procurement-and-delivery/Specs.md,
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §5. Recording the Handover
 * itself only needs Staff and higher
 * (CompanyRole::deliveryAndHandoverRoles()); bypassing the completeness
 * gate is the Owner/Admin-only exception
 * (CompanyRole::jobVariationApprovalRoles(), the same "exception approval"
 * tier used for job variations).
 *
 * "Service jobs require Service Reports then handover" —
 * FINALIZED-DECISIONS.md §10: for a Service job the completeness gate is
 * `SalesOrder::isServiceReportsResolvedForHandover()`, not delivery
 * quantities — a pure service visit may have nothing to physically
 * deliver at all. Every other job type keeps the original
 * `isFullyDelivered()` gate.
 */
class CompleteHandover
{
    public function __construct(
        private DocumentNumberGenerator $numberGenerator,
        private AuditLogger $auditLogger,
    ) {}

    public function complete(
        SalesOrder $salesOrder,
        User $actor,
        ?string $notes = null,
        bool $override = false,
        ?string $overrideReason = null,
    ): HandoverReport {
        if (! $actor->hasCompanyRole($salesOrder->company, ...CompanyRole::deliveryAndHandoverRoles())) {
            throw new RuntimeException('Only Staff and higher may record a handover.');
        }

        if (! $override && ! $this->meetsCompletenessGate($salesOrder)) {
            throw new RuntimeException(
                $salesOrder->job_type === JobType::Service
                    ? 'Handover requires an approved, Resolved service report (with no approved report still Follow-up required), or an Admin/Owner override with a reason.'
                    : 'Handover requires all delivery items to be complete, or an Admin/Owner override with a reason.'
            );
        }

        if ($override) {
            if (! $actor->hasCompanyRole($salesOrder->company, ...CompanyRole::jobVariationApprovalRoles())) {
                throw new RuntimeException('Only Owner/Admin may override the handover completeness requirement.');
            }

            if (blank($overrideReason)) {
                throw new RuntimeException('An override reason is required to bypass the handover completeness requirement.');
            }
        }

        $number = $this->numberGenerator->next($salesOrder->company, 'handover_report');

        $handoverReport = HandoverReport::create([
            'company_id' => $salesOrder->company_id,
            'sales_order_id' => $salesOrder->id,
            'number' => $number,
            'handover_date' => now()->toDateString(),
            'notes' => $notes,
            'is_override' => $override,
            'override_reason' => $override ? $overrideReason : null,
            'created_by_user_id' => $actor->id,
        ]);

        $this->auditLogger->record(
            $salesOrder->company,
            'handover_report.recorded',
            $handoverReport,
            [],
            ['number' => $number],
            $override ? $overrideReason : null,
        );

        return $handoverReport;
    }

    private function meetsCompletenessGate(SalesOrder $salesOrder): bool
    {
        return $salesOrder->job_type === JobType::Service
            ? $salesOrder->isServiceReportsResolvedForHandover()
            : $salesOrder->isFullyDelivered();
    }
}
