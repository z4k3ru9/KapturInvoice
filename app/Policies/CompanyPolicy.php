<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;

/**
 * Company-level abilities that aren't plain create/update/delete on a
 * company-scoped record (those are covered by the generic Gate::before
 * hook in App\Providers\AppServiceProvider). See
 * docs/rebuild/specs/01-company-foundation/Specs.md and
 * docs/rebuild/Specs.md §10 "Protected actions".
 */
class CompanyPolicy
{
    /** "View settings: Owner/Admin ... Auditor never." */
    public function viewSettings(User $user, Company $company): bool
    {
        return $user->hasCompanyRole($company, ...CompanyRole::settingsRoles());
    }

    /** Inviting/disabling internal users is a settings-adjacent, Owner/Admin action. */
    public function manageMembership(User $user, Company $company): bool
    {
        return $user->hasCompanyRole($company, ...CompanyRole::settingsRoles());
    }

    /** "Physical deletion: Owner-controlled only ... with reason and audit event." */
    public function physicalDelete(User $user, Company $company): bool
    {
        return $user->hasCompanyRole($company, CompanyRole::Owner);
    }
}
