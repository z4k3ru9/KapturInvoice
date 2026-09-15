<?php

namespace App\Actions\Sales;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Services\AuditLogger;
use RuntimeException;

/**
 * "Block approval unless milestones equal the current approved job
 * value." — docs/rebuild/specs/FINALIZED-DECISIONS.md §4,
 * docs/rebuild/specs/03-sales-and-job/Specs.md. Draft milestones may
 * differ temporarily (Specs.md), but approving the job itself enforces
 * the sum exactly — both an over-funded and an under-funded milestone
 * set are rejected, not just a shortfall.
 */
class ApproveSalesOrder
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function approve(SalesOrder $salesOrder): SalesOrder
    {
        if (! $salesOrder->status->canTransitionTo(SalesOrderStatus::Approved)) {
            throw new RuntimeException(
                "Job cannot move from {$salesOrder->status->value} to approved."
            );
        }

        $salesOrder->loadMissing('milestones');

        $milestonesTotal = round((float) $salesOrder->milestones->sum('amount'), 2);
        $jobValue = round((float) $salesOrder->approved_value, 2);

        if (abs($milestonesTotal - $jobValue) > 0.01) {
            throw new RuntimeException(
                "Milestones total ({$milestonesTotal}) must equal the current approved job value ({$jobValue})."
            );
        }

        $before = ['status' => $salesOrder->status->value, 'approved_at' => null];

        $salesOrder->forceFill([
            'status' => SalesOrderStatus::Approved,
            'approved_at' => now(),
        ])->save();

        $this->auditLogger->record(
            $salesOrder->company,
            'sales_order.approved',
            $salesOrder,
            $before,
            ['status' => $salesOrder->status->value, 'approved_at' => $salesOrder->approved_at],
        );

        return $salesOrder;
    }
}
