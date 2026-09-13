<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The only writer of `audit_events` — every privileged action (membership
 * disable, period reopen, and future issuance/void/amend/override actions)
 * must go through this rather than writing the table directly, so the log
 * stays a reliable append-only record. See
 * docs/rebuild/specs/01-company-foundation/Specs.md and
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §2.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(
        Company $company,
        string $action,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        ?string $reason = null,
    ): AuditEvent {
        return AuditEvent::create([
            'company_id' => $company->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $subject?->getMorphClass(),
            'entity_id' => $subject?->getKey(),
            'reason' => $reason,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
