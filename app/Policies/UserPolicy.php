<?php

namespace App\Policies;

use App\Models\User;
use Filament\Facades\Filament;

/**
 * Wires "Owner/Admin invite internal users" (FINALIZED-DECISIONS.md §12)
 * into the Users/Team resource. App\Models\User deliberately doesn't use
 * App\Models\Concerns\BelongsToCompany (a user belongs to companies via
 * the `company_user` pivot, not a single company_id — see UserResource's
 * docblock), so it falls outside AppServiceProvider's Gate::before hook
 * that already covers every other company-scoped model's create/update/
 * delete. Without this Policy, any authenticated company member —
 * Auditor included, who should be read-only everywhere per
 * docs/rebuild/Specs.md §10 — could create/edit/delete Users, toggle
 * is_super_admin, and attach/detach company memberships with no
 * authorization check at all. Reuses CompanyPolicy::manageMembership,
 * which already existed for exactly this ability but was never
 * consulted from here.
 */
class UserPolicy
{
    public function create(User $user): bool
    {
        return $this->canManageMembership($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->canManageMembership($user);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->canManageMembership($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canManageMembership($user);
    }

    private function canManageMembership(User $user): bool
    {
        $tenant = Filament::hasTenancy() ? Filament::getTenant() : null;

        if (! $tenant) {
            return false;
        }

        return $user->can('manageMembership', $tenant);
    }
}
