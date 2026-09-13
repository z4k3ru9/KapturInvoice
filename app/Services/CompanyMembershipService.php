<?php

namespace App\Services;

use App\Enums\CompanyRole;
use App\Mail\UserInvitationMail;
use App\Models\Company;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Internal-user membership lifecycle: invite by expiring email link, accept
 * (setting a local password), and disable (blocks access immediately while
 * preserving history — never deletes the membership row or the user's
 * authored records). See docs/rebuild/specs/FINALIZED-DECISIONS.md §1 and
 * docs/rebuild/specs/01-company-foundation/Specs.md.
 */
class CompanyMembershipService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function invite(Company $company, string $email, CompanyRole $role, ?User $invitedBy = null, int $expiresInDays = 7): UserInvitation
    {
        $invitation = UserInvitation::create([
            'company_id' => $company->id,
            'email' => $email,
            'role' => $role,
            'token' => Str::random(48),
            'invited_by' => $invitedBy?->id,
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        Mail::to($email)->queue(new UserInvitationMail($invitation));

        return $invitation;
    }

    /**
     * Accepts a usable invitation, creating the user (with the supplied
     * name/password) if none with this email exists yet, and attaching an
     * active membership at the invited role.
     */
    public function accept(UserInvitation $invitation, string $name, string $password): User
    {
        if (! $invitation->isUsable()) {
            throw new RuntimeException('This invitation has expired or was already used.');
        }

        $user = User::query()->firstOrNew(['email' => $invitation->email]);
        $user->name = $name;
        $user->password = Hash::make($password);
        $user->save();

        $user->companies()->syncWithoutDetaching([
            $invitation->company_id => ['role' => $invitation->role->value, 'is_active' => true],
        ]);

        $invitation->forceFill(['accepted_at' => now()])->save();

        return $user;
    }

    /**
     * Blocks access immediately without deleting the membership row or any
     * record the user authored — the PRD is explicit that disabling
     * membership must preserve history.
     */
    public function disable(Company $company, User $user, User $actor, string $reason): void
    {
        $company->users()->updateExistingPivot($user->id, ['is_active' => false]);

        $this->auditLogger->record(
            $company,
            'membership.disabled',
            $user,
            before: ['is_active' => true],
            after: ['is_active' => false],
            reason: $reason,
        );
    }

    public function reenable(Company $company, User $user, User $actor, string $reason): void
    {
        $company->users()->updateExistingPivot($user->id, ['is_active' => true]);

        $this->auditLogger->record(
            $company,
            'membership.reenabled',
            $user,
            before: ['is_active' => false],
            after: ['is_active' => true],
            reason: $reason,
        );
    }
}
