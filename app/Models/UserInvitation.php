<?php

namespace App\Models;

use App\Enums\CompanyRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An internal-user invitation by expiring email link
 * (docs/rebuild/specs/FINALIZED-DECISIONS.md §1). Distinct from
 * App\Models\Invitation, which is the unauthenticated client-portal magic
 * link. Written/consumed through App\Services\CompanyMembershipService.
 */
#[Fillable(['company_id', 'email', 'role', 'token', 'invited_by', 'expires_at', 'accepted_at'])]
class UserInvitation extends Model
{
    protected function casts(): array
    {
        return [
            'role' => CompanyRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return ! is_null($this->accepted_at);
    }

    public function isUsable(): bool
    {
        return ! $this->isExpired() && ! $this->isAccepted();
    }
}
