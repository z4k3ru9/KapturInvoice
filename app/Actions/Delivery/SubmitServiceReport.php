<?php

namespace App\Actions\Delivery;

use App\Enums\CompanyRole;
use App\Enums\ServiceReportStatus;
use App\Models\ServiceReport;
use App\Models\User;
use RuntimeException;

/**
 * The `Draft -> Submitted` step (FINALIZED-DECISIONS.md §10). Mirrors
 * App\Actions\Procurement\SubmitVendorBill.
 */
class SubmitServiceReport
{
    public function submit(ServiceReport $report, User $actor): ServiceReport
    {
        if (! $actor->hasCompanyRole($report->company, ...CompanyRole::deliveryAndHandoverRoles())) {
            throw new RuntimeException('Only Staff and higher may submit a service report.');
        }

        if (! $report->status->canTransitionTo(ServiceReportStatus::Submitted)) {
            throw new RuntimeException("A service report in status [{$report->status->value}] cannot be submitted.");
        }

        $report->forceFill(['status' => ServiceReportStatus::Submitted])->save();

        return $report->fresh();
    }
}
