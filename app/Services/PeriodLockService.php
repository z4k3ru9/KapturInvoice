<?php

namespace App\Services;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * A soft period lock: dates on or before `period_locked_through` are
 * closed. Only an Owner/Accountant may reopen a closed period, and only
 * with a reason (audited) — see
 * docs/rebuild/specs/01-company-foundation/Specs.md and
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §2. Closing (moving the lock
 * forward) is not itself destructive, so any settings-capable role may do
 * it; only reopening (moving it back or clearing it) is restricted.
 */
class PeriodLockService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function isLocked(Company $company, CarbonInterface $date): bool
    {
        $lockedThrough = $company->settings?->period_locked_through;

        // Compare by calendar date only: `$date` is often "now" (a real
        // time-of-day), and `period_locked_through` is a date with no
        // meaningful time component — a naive Carbon `lte()` would treat
        // "today, any time after midnight" as after "today" and wrongly
        // report it unlocked.
        return $lockedThrough !== null && $date->toDateString() <= $lockedThrough->toDateString();
    }

    public function close(Company $company, CarbonInterface $throughDate): void
    {
        $company->settings()->updateOrCreate([], ['period_locked_through' => $throughDate->toDateString()]);
    }

    public function reopen(Company $company, User $actor, string $reason): void
    {
        if (! $actor->hasCompanyRole($company, ...CompanyRole::periodReopenRoles())) {
            throw new RuntimeException('Only an Owner or Accountant may reopen a closed period.');
        }

        $before = $company->settings?->period_locked_through?->toDateString();

        $company->settings()->updateOrCreate([], ['period_locked_through' => null]);

        $this->auditLogger->record(
            $company,
            'period.reopened',
            $company,
            before: ['period_locked_through' => $before],
            after: ['period_locked_through' => null],
            reason: $reason,
        );
    }
}
