<?php

namespace App\Livewire;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\CompanyMembershipService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack "Users & Roles" company-scoped member register — see
 * App\Livewire\TallStackQuotations's docblock for the established pattern
 * this follows. This area has no dedicated Stitch phase number (it was in
 * the "Deferred, no Stitch mockup" list until a mockup was generated —
 * see docs/rebuild/outputs/ui-rebuild/25-tallstack-full-rebuild-plan.md and
 * docs/rebuild/outputs/ui-rebuild/26-stitch-missing-screens-prompts.md prompt 11).
 *
 * Every row here is a `company_user` pivot (App\Enums\CompanyRole in its
 * `role` column, plus an `is_active` flag) — a person can hold a
 * different role in each company they belong to, so this is never a
 * global user list. Reuses App\Services\CompanyMembershipService::invite()/
 * disable()/reenable() unmodified for every membership-lifecycle mutation
 * — this component only ever performs the same raw pivot `role` update the
 * equivalent pre-TallStackUI Filament users resource's Companies relation
 * manager EditAction already performed (there is no
 * dedicated domain Action class for a bare role change), and both are
 * gated by the exact same App\Policies\CompanyPolicy::manageMembership
 * check (Owner/Admin only, via App\Enums\CompanyRole::settingsRoles()).
 *
 * Revoking a *pending* (not yet accepted) invitation has no existing
 * domain service method to reuse — App\Models\UserInvitation is only ever
 * written by CompanyMembershipService::invite()/accept(). Deleting an
 * unaccepted invitation row is not a financial/issued record under
 * CLAUDE.md's "never physically delete" guard (that guard covers issued
 * documents/verified payments/receipts/snapshots/audit events — an
 * unaccepted email invite is none of those), so it's a plain delete here
 * rather than a new soft-lifecycle method invented for this phase.
 */
#[Layout('components.tallstack.app')]
class TallStackUsers extends Component
{
    use Interactions;

    public Company $company;

    public string $search = '';

    // Invite modal state.
    public bool $showInviteModal = false;

    public string $invite_email = '';

    public string $invite_role = CompanyRole::Staff->value;

    // Edit-role modal state — Name/Email are read-only display only, never bound for editing.
    public bool $showEditRoleModal = false;

    public ?int $editingUserId = null;

    public string $editingUserName = '';

    public string $editingUserEmail = '';

    public string $edit_role = CompanyRole::Staff->value;

    // Remove-member modal state (reuses CompanyMembershipService::disable(), which requires a reason).
    public bool $showRemoveModal = false;

    public ?int $removingUserId = null;

    public string $removingUserName = '';

    public string $removeReason = '';

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        // Same "Owner/Admin only" boundary as the equivalent
        // pre-TallStackUI Filament users resource — checked via the
        // exact same CompanyPolicy::manageMembership ability rather than
        // a re-derived role list, and re-checked here (not just relied on
        // via a hidden nav item) since this route sits outside the
        // Filament panel's own tenant-gated request lifecycle.
        abort_unless(auth()->user()->can('manageMembership', $company), 403);

        app(Tenancy::class)->set($company);
    }

    public function openInviteModal(): void
    {
        $this->invite_email = '';
        $this->invite_role = CompanyRole::Staff->value;
        $this->showInviteModal = true;
    }

    public function invite(): void
    {
        $this->authorize('manageMembership', $this->company);

        $data = $this->validate([
            'invite_email' => ['required', 'email', 'max:255'],
            'invite_role' => ['required', Rule::enum(CompanyRole::class)],
        ], attributes: ['invite_email' => 'email', 'invite_role' => 'role']);

        try {
            app(CompanyMembershipService::class)->invite(
                $this->company,
                $data['invite_email'],
                CompanyRole::from($data['invite_role']),
                auth()->user(),
            );

            $this->showInviteModal = false;
            $this->toast()->success('Invitation sent.', "An invite email was sent to {$data['invite_email']}.")->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not send invitation', $e->getMessage())->send();
        }
    }

    public function openEditRoleModal(int $userId): void
    {
        $user = $this->scopedUser($userId);

        if (! $user) {
            return;
        }

        $this->editingUserId = $user->id;
        $this->editingUserName = $user->name;
        $this->editingUserEmail = $user->email;
        $this->edit_role = $user->companyRole($this->company)?->value ?? CompanyRole::Staff->value;
        $this->showEditRoleModal = true;
    }

    public function saveRole(): void
    {
        $this->authorize('manageMembership', $this->company);

        $data = $this->validate([
            'edit_role' => ['required', Rule::enum(CompanyRole::class)],
        ], attributes: ['edit_role' => 'role']);

        $user = $this->scopedUser($this->editingUserId);

        if (! $user) {
            $this->showEditRoleModal = false;

            return;
        }

        // A company always has at least one Owner (prompt 11: "Empty
        // state: not applicable — a company always has at least one
        // Owner"). Nothing upstream enforces that invariant on a bare
        // pivot update, so this UI-level guard stops the one obviously
        // destructive action this screen could otherwise perform (locking
        // every member out of Owner-only actions company-wide) — it never
        // widens or narrows any role's actual permission boundary.
        if ($user->companyRole($this->company) === CompanyRole::Owner
            && CompanyRole::from($data['edit_role']) !== CompanyRole::Owner
            && $this->activeOwnerCount() <= 1) {
            $this->toast()->error('Could not update role', 'This is the only Owner — assign another member as Owner first.')->send();

            return;
        }

        $this->company->users()->updateExistingPivot($user->id, ['role' => $data['edit_role']]);

        $this->showEditRoleModal = false;
        $this->toast()->success('Role updated.', "{$user->name} is now {$data['edit_role']}.")->send();
    }

    public function openRemoveModal(int $userId): void
    {
        $user = $this->scopedUser($userId);

        if (! $user) {
            return;
        }

        $this->removingUserId = $user->id;
        $this->removingUserName = $user->name;
        $this->removeReason = '';
        $this->showRemoveModal = true;
    }

    public function removeMember(): void
    {
        $this->authorize('manageMembership', $this->company);

        $data = $this->validate([
            'removeReason' => ['required', 'string', 'max:500'],
        ], attributes: ['removeReason' => 'reason']);

        $user = $this->scopedUser($this->removingUserId);

        if (! $user) {
            $this->showRemoveModal = false;

            return;
        }

        if ($user->companyRole($this->company) === CompanyRole::Owner && $this->activeOwnerCount() <= 1) {
            $this->toast()->error('Could not remove member', 'This is the only Owner — assign another member as Owner first.')->send();

            return;
        }

        app(CompanyMembershipService::class)->disable($this->company, $user, auth()->user(), $data['removeReason']);

        $this->showRemoveModal = false;
        $this->toast()->success('Member removed.', "{$user->name} no longer has access to {$this->company->name}.")->send();
    }

    public function restoreMember(int $userId): void
    {
        $this->authorize('manageMembership', $this->company);

        $user = User::query()->find($userId);

        if (! $user || ! $this->company->users()->whereKey($userId)->exists()) {
            return;
        }

        app(CompanyMembershipService::class)->reenable($this->company, $user, auth()->user(), 'Restored via Users & Roles.');

        $this->toast()->success('Access restored.', "{$user->name} can access {$this->company->name} again.")->send();
    }

    public function revokeInvitation(int $invitationId): void
    {
        $this->authorize('manageMembership', $this->company);

        $invitation = UserInvitation::query()
            ->where('company_id', $this->company->id)
            ->whereKey($invitationId)
            ->whereNull('accepted_at')
            ->first();

        if (! $invitation) {
            return;
        }

        $invitation->delete();

        $this->toast()->success('Invitation revoked.')->send();
    }

    /** Never trust a bare User::find() — re-check company membership explicitly, same reasoning as TallStackQuotations::findScoped(). */
    private function scopedUser(?int $userId): ?User
    {
        if (! $userId) {
            return null;
        }

        return $this->company->users()->whereKey($userId)->first();
    }

    private function activeOwnerCount(): int
    {
        return DB::table('company_user')
            ->where('company_id', $this->company->id)
            ->where('role', CompanyRole::Owner->value)
            ->where('is_active', true)
            ->count();
    }

    public function render(): View
    {
        // Queried directly against the pivot table rather than through
        // App\Models\Company::users() (which doesn't list `is_active` in
        // its own withPivot([...]) — only `role` — so it can't be read off
        // a hydrated pivot without changing that model's relation
        // definition, which this presentation-layer phase avoids).
        $members = DB::table('company_user')
            ->join('users', 'users.id', '=', 'company_user.user_id')
            ->where('company_user.company_id', $this->company->id)
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('users.name', 'like', "%{$this->search}%")
                        ->orWhere('users.email', 'like', "%{$this->search}%")
                        ->orWhere('company_user.role', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('users.name')
            ->select([
                'users.id', 'users.name', 'users.email', 'users.is_super_admin',
                'company_user.role', 'company_user.is_active',
            ])
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'is_super_admin' => (bool) $row->is_super_admin,
                'role' => CompanyRole::tryFrom($row->role),
                'is_active' => (bool) $row->is_active,
                'initials' => collect(explode(' ', $row->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode(''),
            ]);

        $invitations = UserInvitation::query()
            ->where('company_id', $this->company->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->when($this->search, fn ($q) => $q->where('email', 'like', "%{$this->search}%"))
            ->orderBy('email')
            ->get();

        return view('livewire.tallstack-users', [
            'members' => $members,
            'invitations' => $invitations,
            'roles' => CompanyRole::cases(),
            'activeCount' => $members->where('is_active', true)->count(),
            'invitedCount' => $invitations->count(),
            'roleColors' => $this->roleColors(),
        ])->layoutData([
            'company' => $this->company,
            'active' => 'users',
            'title' => 'Users & Roles',
        ]);
    }

    /**
     * Role colors, not status colors — deliberately not run through
     * App\Support\TallStack\StatusColor::map(), which translates a
     * Filament *status* semantic (gray/info/success/warning/danger) and
     * has no notion of "one of six roles". Per prompt 11: "Owner=brand
     * accent, Admin=blue, Accountant=teal, Sales=amber, Staff=gray,
     * Auditor=slate" — Owner alone uses the tenant's own primary brand
     * color (via inline style, not a Tailwind class) since it's the only
     * role tied to the company's own identity; the rest are plain
     * TallStackUI <x-badge> palette names.
     *
     * @return array<string, string>
     */
    private function roleColors(): array
    {
        return [
            CompanyRole::Owner->value => 'primary',
            CompanyRole::Admin->value => 'blue',
            CompanyRole::Accountant->value => 'teal',
            CompanyRole::Sales->value => 'amber',
            CompanyRole::Staff->value => 'gray',
            CompanyRole::Auditor->value => 'slate',
        ];
    }
}
