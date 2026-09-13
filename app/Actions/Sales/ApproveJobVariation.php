<?php

namespace App\Actions\Sales;

use App\Enums\CompanyRole;
use App\Enums\JobVariationType;
use App\Models\JobVariation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "Support overrun, out-of-scope work, and item substitutions only
 * through Owner/Admin approval with reason. Preserve original approved
 * values and variation history." —
 * docs/rebuild/specs/03-sales-and-job/Specs.md,
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §4. Never mutates a prior
 * JobVariation row; each approval inserts a new one and advances the
 * job's `approved_value` by the signed `$amount` (positive for an
 * overrun/added scope, negative for a substitution that reduces value).
 */
class ApproveJobVariation
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function approve(
        SalesOrder $salesOrder,
        User $approver,
        JobVariationType $type,
        string $reason,
        float $amount,
    ): JobVariation {
        if (! $approver->hasCompanyRole($salesOrder->company, ...CompanyRole::jobVariationApprovalRoles())) {
            throw new RuntimeException('Only Owner/Admin may approve a job variation.');
        }

        $valueBefore = round((float) $salesOrder->approved_value, 2);
        $valueAfter = round($valueBefore + $amount, 2);

        $variation = DB::transaction(function () use ($salesOrder, $approver, $type, $reason, $amount, $valueBefore, $valueAfter) {
            $variation = JobVariation::create([
                'sales_order_id' => $salesOrder->id,
                'approved_by_user_id' => $approver->id,
                'type' => $type,
                'reason' => $reason,
                'amount' => $amount,
                'value_before' => $valueBefore,
                'value_after' => $valueAfter,
                'approved_at' => now(),
            ]);

            $salesOrder->forceFill(['approved_value' => $valueAfter])->save();

            return $variation;
        });

        $this->auditLogger->record(
            $salesOrder->company,
            'job_variation.approved',
            $salesOrder,
            ['approved_value' => $valueBefore],
            ['approved_value' => $valueAfter],
            $reason,
        );

        return $variation;
    }
}
