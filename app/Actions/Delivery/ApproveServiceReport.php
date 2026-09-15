<?php

namespace App\Actions\Delivery;

use App\Enums\CompanyRole;
use App\Enums\ServiceReportStatus;
use App\Models\ServiceReport;
use App\Models\User;
use App\Services\AuditLogger;
use RuntimeException;

/**
 * The `Submitted -> Approved` step. "Approval snapshots the report, and
 * approved reports are immutable and never physically deleted." —
 * FINALIZED-DECISIONS.md §10. The frozen `snapshot` mirrors
 * App\Models\Receipt's own issuance snapshot: whatever the printed
 * document renders and the service-job handover gate
 * (App\Models\SalesOrder::isServiceReportsResolvedForHandover()) consult
 * can never silently change after this point, even if the underlying
 * columns were ever touched directly.
 */
class ApproveServiceReport
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function approve(ServiceReport $report, User $approver): ServiceReport
    {
        if (! $approver->hasCompanyRole($report->company, ...CompanyRole::deliveryAndHandoverRoles())) {
            throw new RuntimeException('Only Staff and higher may approve a service report.');
        }

        if (! $report->status->canTransitionTo(ServiceReportStatus::Approved)) {
            throw new RuntimeException("A service report in status [{$report->status->value}] cannot be approved.");
        }

        $report->forceFill([
            'status' => ServiceReportStatus::Approved,
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
            'snapshot' => [
                'number' => $report->number,
                'service_date' => $report->service_date?->toDateString(),
                'technician_user_id' => $report->technician_user_id,
                'external_technician_name' => $report->external_technician_name,
                'problem_reported' => $report->problem_reported,
                'diagnosis' => $report->diagnosis,
                'action_taken' => $report->action_taken,
                'parts_used' => $report->parts_used,
                'result' => $report->result?->value,
                'follow_up_notes' => $report->follow_up_notes,
                'customer_acknowledgement_name' => $report->customer_acknowledgement_name,
                'approved_at' => now()->toIso8601String(),
            ],
        ])->save();

        $this->auditLogger->record(
            $report->company,
            'service_report.approved',
            $report,
            ['status' => ServiceReportStatus::Submitted->value],
            ['status' => ServiceReportStatus::Approved->value],
        );

        return $report->fresh();
    }
}
