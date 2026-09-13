<?php

namespace App\Actions\Sales;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use RuntimeException;

/**
 * Every job status change except Draft->Approved goes through here (that
 * one is ApproveSalesOrder's job, since it also validates the milestone
 * total). Backed by `SalesOrderStatus::canTransitionTo()` — the state
 * matrix from docs/rebuild/specs/03-sales-and-job/Specs.md.
 */
class TransitionSalesOrderStatus
{
    public function transition(SalesOrder $salesOrder, SalesOrderStatus $to): SalesOrder
    {
        if ($to === SalesOrderStatus::Approved) {
            throw new RuntimeException('Use ApproveSalesOrder to move a job to Approved.');
        }

        if (! $salesOrder->status->canTransitionTo($to)) {
            throw new RuntimeException(
                "Job cannot move from {$salesOrder->status->value} to {$to->value}."
            );
        }

        $salesOrder->status = $to;

        if ($to === SalesOrderStatus::Cancelled) {
            $salesOrder->cancelled_at = now();
        }

        $salesOrder->save();

        return $salesOrder;
    }
}
