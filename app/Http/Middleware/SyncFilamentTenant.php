<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\Tenancy;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one bridge between Filament's own tenant resolution (still internal
 * to the still-installed `/admin` panel) and App\Support\Tenancy\Tenancy —
 * the app-owned tenant store App\Models\Concerns\BelongsToCompany and the
 * company-role Gate now read instead of the Filament facade (see the
 * Filament-removal Phase A). Registered as tenant middleware on
 * `AdminPanelProvider`'s panel; without it, the admin panel's own data
 * would silently stop being company-scoped once BelongsToCompany stopped
 * reading `Filament::getTenant()` directly — company isolation is this
 * app's most safety-critical property, so this stays wired in until
 * Phase B removes the panel (and this file) entirely.
 */
class SyncFilamentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        app(Tenancy::class)->set(Filament::getTenant());

        return $next($request);
    }
}
