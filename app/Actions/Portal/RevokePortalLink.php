<?php

namespace App\Actions\Portal;

use App\Models\PortalLink;
use App\Models\User;
use App\Services\AuditLogger;
use RuntimeException;

/**
 * Revokes a `PortalLink` — once revoked, `PortalLink::isActive()` is false
 * and `App\Livewire\Portal\ClientPortalHome` 404s on it exactly like a
 * nonexistent or cross-company link (see
 * docs/rebuild/specs/06-documents-portal-reporting/Specs.md "Portal cannot
 * expose disabled actions through stale links").
 */
class RevokePortalLink
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function revoke(PortalLink $link, User $actor, ?string $reason = null): PortalLink
    {
        if ($link->revoked_at !== null) {
            throw new RuntimeException('This portal link is already revoked.');
        }

        $link->forceFill(['revoked_at' => now()])->save();

        $this->auditLogger->record(
            $link->company,
            'portal_link.revoked',
            $link,
            ['revoked_at' => null],
            ['revoked_at' => $link->revoked_at->toIso8601String()],
            $reason,
        );

        return $link->fresh();
    }
}
