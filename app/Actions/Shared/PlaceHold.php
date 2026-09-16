<?php

namespace App\Actions\Shared;

use App\Enums\CompanyRole;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\AuditLogger;
use RuntimeException;

/**
 * Pauses the status-transition automation (Invoice auto-Overdue, recurring
 * auto-generate/issue/send, SalesOrder auto-close-operationally) on one
 * specific record — the override lever for the real case this comes up:
 * moderation, or a revision that needs approval before anything is amended
 * automatically. Every automated query added alongside this action scopes
 * with `->notHeld()` (App\Models\Concerns\Holdable), so a held record is
 * simply skipped on the next run/event until released.
 *
 * Deliberately NOT a raw status-dropdown override — see this action's own
 * companion, App\Actions\Shared\ReleaseHold, and
 * App\Models\Concerns\Holdable's docblock.
 */
class PlaceHold
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function hold(Invoice|SalesOrder $subject, User $actor, string $reason): Invoice|SalesOrder
    {
        if (! $actor->hasCompanyRole($subject->company, ...CompanyRole::holdApprovalRoles())) {
            throw new RuntimeException('Only Owner/Admin may place a hold.');
        }

        if (trim($reason) === '') {
            throw new RuntimeException('A reason is required to place a hold.');
        }

        if ($subject->isHeld()) {
            throw new RuntimeException('This record already has a hold in place.');
        }

        $subject->forceFill([
            'held_at' => now(),
            'held_reason' => $reason,
            'held_by' => $actor->id,
        ])->save();

        $this->auditLogger->record(
            $subject->company,
            $subject instanceof Invoice ? 'invoice.held' : 'sales_order.held',
            $subject,
            ['held_at' => null],
            ['held_at' => $subject->held_at?->toIso8601String()],
            $reason,
        );

        return $subject->fresh();
    }
}
