<?php

namespace App\Support\Tenancy;

use App\Models\Company;

/**
 * The app's own tenant-context holder — originally introduced (Phase A of
 * the Filament removal) as the app-owned replacement for the
 * tenant-storage role the Filament facade used to play (its
 * `setTenant()`/`getTenant()`/`hasTenancy()` methods, still bridged from
 * the Filament admin panel at the time). Registered as a singleton (see
 * `App\Providers\AppServiceProvider::register()`), so it holds exactly one
 * "current company" for the life of a request — plain in-memory state.
 *
 * Every `App\Livewire\TallStack*` page's `mount()` calls `set()` once it
 * resolves the company from the route; `App\Models\Concerns\
 * BelongsToCompany`, the `Gate::before` company-role check in
 * `AppServiceProvider`, `App\Policies\UserPolicy`, and every public
 * PDF/download controller read it via `get()`/`has()`. As of the
 * Filament-removal Phase B, the Filament admin panel (and its own
 * tenant-resolution bridge) is gone entirely — this is now the only
 * tenant-context mechanism in the app.
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
