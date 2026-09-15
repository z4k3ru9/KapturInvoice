<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registers the current tenant's own brand color as the panel's `primary`
 * color — see docs/rebuild/outputs/18-stitch-ui-gap-analysis/01-shell-dashboard.md
 * §0 step 2 (S2). `AdminPanelProvider::panel()`'s own `->colors()` call runs
 * before the tenant is resolved (there's no request yet), so it can only
 * set a pre-tenant fallback (e.g. the login page) — this tenant-scoped
 * middleware is the only place a per-company color can actually apply.
 */
class ApplyCompanyBrand
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = Filament::getTenant();

        if ($company && filled($company->primary_color)) {
            FilamentColor::register([
                'primary' => Color::hex($company->primary_color),
            ]);
        }

        return $next($request);
    }
}
