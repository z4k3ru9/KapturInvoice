<?php

namespace App\Support\Tenancy;

use App\Models\Company;

/**
 * The app-owned replacement for the tenant-storage role of the Filament
 * facade (its `setTenant()`/`getTenant()`/`hasTenancy()` methods). Registered
 * as a singleton (see `App\Providers\AppServiceProvider::register()`), so
 * it holds exactly one "current company" for the life of a request —
 * plain in-memory state, nothing Filament-specific about the storage
 * itself.
 *
 * Every `App\Livewire\TallStack*` page's `mount()` calls `set()` once it
 * resolves the company from the route; `App\Models\Concerns\
 * BelongsToCompany`, the `Gate::before` company-role check in
 * `AppServiceProvider`, `App\Policies\UserPolicy`, and every public
 * PDF/download controller read it via `get()`/`has()` instead of the
 * Filament facade. The still-installed `/admin` Filament panel keeps its
 * own internal tenant resolution (still read via the Filament facade) — see
 * `App\Providers\Filament\AdminPanelProvider`'s tenant middleware for the
 * one bridge that copies Filament's resolved tenant into this class, so
 * company scoping keeps working there too until the panel itself is
 * removed.
 */
class Tenancy
{
    private ?Company $tenant = null;

    public function set(?Company $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Company
    {
        return $this->tenant;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }
}
