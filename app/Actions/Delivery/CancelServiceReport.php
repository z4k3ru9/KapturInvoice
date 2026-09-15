<?php

namespace App\Actions\Delivery;

use App\Enums\CompanyRole;
use App\Enums\ServiceReportStatus;
use App\Models\ServiceReport;
use App\Models\User;
use App\Services\AuditLogger;
use RuntimeException;

/**
 * "Cancelled from any non-approved state." — FINALIZED-DECISIONS.md §10.
 * A cancelled report is kept (never physically deleted) and simply stops
 * counting toward the service-job handover gate.
 */
class CancelServiceReport
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function cancel(ServiceReport $report, User $actor, ?string $reason = null): ServiceReport
    {
        if (! $actor->hasCompanyRole($report->company, ...CompanyRole::deliveryAndHandoverRoles())) {
            throw new RuntimeException('Only Staff and higher may cancel a service report.');
        }

        if (! $report->status->canTransitionTo(ServiceReportStatus::Cancelled)) {
            throw new RuntimeException("A service report in status [{$report->status->value}] cannot be cancelled.");
        }

        $previousStatus = $report->status;

        $report->forceFill([
            'status' => ServiceReportStatus::Cancelled,
            'cancelled_at' => now(),
        ])->save();

        $this->auditLogger->record(
            $report->company,
            'service_report.cancelled',
            $report,
            ['status' => $previousStatus->value],
            ['status' => ServiceReportStatus::Cancelled->value],
            $reason,
        );

        return $report->fresh();
    }
}
