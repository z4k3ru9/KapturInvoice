<?php

namespace App\Filament\Pages\Settings\Concerns;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

/**
 * "View settings: Owner/Admin according to setting sensitivity; Auditor
 * never" (docs/rebuild/Specs.md §10) — applied to every Settings page.
 * Company-scoped model mutations already go through the Gate::before hook
 * in App\Providers\AppServiceProvider; Settings pages aren't Eloquent
 * records, so they need this explicit check instead.
 */
trait RestrictsToSettingsRoles
{
    public static function canAccess(array $parameters = []): bool
    {
        $user = Auth::user();
        $tenant = Filament::getTenant();

        if (! $user || ! $tenant) {
            return false;
        }

        return $user->can('viewSettings', $tenant);
    }
}
