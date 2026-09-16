<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\CompanyRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

#[Fillable(['name', 'email', 'password', 'is_super_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof Company || ! $tenant->is_active) {
            return false;
        }

        return $this->is_super_admin
            || $this->companies()->whereKey($tenant->getKey())->wherePivot('is_active', true)->exists();
    }

    /**
     * This user's membership role for the given company, or null if they
     * have no (active) membership — including a super admin, who bypasses
     * role checks entirely rather than holding one.
     */
    public function companyRole(Model $company): ?CompanyRole
    {
        $pivot = $this->companies()
            ->wherePivot('is_active', true)
            ->whereKey($company->getKey())
            ->first()
            ?->pivot;

        return $pivot ? CompanyRole::tryFrom($pivot->role) : null;
    }

    /**
     * True for a super admin (who bypasses per-company role checks
     * entirely) or an active member holding one of the given roles.
     * Central check used by the Gate::before hook in AppServiceProvider
     * and by policies/UI — see
     * docs/rebuild/specs/01-company-foundation/Specs.md.
     */
    public function hasCompanyRole(Model $company, CompanyRole ...$roles): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        $role = $this->companyRole($company);

        return $role !== null && in_array($role, $roles, true);
    }
}
