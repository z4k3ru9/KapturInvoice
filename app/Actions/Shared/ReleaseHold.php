<?php

namespace App\Actions\Shared;

use App\Enums\CompanyRole;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\AuditLogger;
use RuntimeException;

/**
 * Companion to App\Actions\Shared\PlaceHold — resumes status-transition
 * automation on a record by clearing its hold. Same role tier as placing
 * one; the reason is optional here (placing a hold requires justifying
 * WHY automation is paused, releasing it doesn't carry the same audit
 * weight, but is still logged).
 */
class ReleaseHold
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function release(Invoice|SalesOrder $subject, User $actor, ?string $reason = null): Invoice|SalesOrder
    {
        if (! $actor->hasCompanyRole($subject->company, ...CompanyRole::holdApprovalRoles())) {
            throw new RuntimeException('Only Owner/Admin may release a hold.');
        }

        if (! $subject->isHeld()) {
            throw new RuntimeException('This record has no hold in place.');
        }

        $before = $subject->held_at?->toIso8601String();

        $subject->forceFill([
            'held_at' => null,
            'held_reason' => null,
            'held_by' => null,
        ])->save();

        $this->auditLogger->record(
            $subject->company,
            $subject instanceof Invoice ? 'invoice.hold_released' : 'sales_order.hold_released',
            $subject,
            ['held_at' => $before],
            ['held_at' => null],
            $reason,
        );

        return $subject->fresh();
    }
}
