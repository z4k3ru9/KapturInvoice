<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Tenancy\EditCompanyProfile;
use App\Filament\Pages\Tenancy\RegisterCompany;
use App\Http\Middleware\ApplyCompanyBrand;
use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Pre-tenant fallback only (the login page, tenant registration
            // — there's no resolved Company yet at this point in the
            // request lifecycle). The real per-tenant color is applied by
            // ApplyCompanyBrand below, via `FilamentColor::register()`.
            ->colors([
                'primary' => Color::hex('#E63934'),
            ])
            // Sidebar order per docs/rebuild/DESIGN.md §2 / the Stitch
            // shell reference. Filament does NOT push an unlisted group to
            // the end — an unregistered group keeps its own default
            // (alphabetical) sort weight, which sorted "Billing"/"Clients"/
            // "Documents"/"Expenses" ahead of "Sales" here despite this
            // array's intent (confirmed by rendering the panel). Every
            // group actually in use must be listed explicitly to control
            // the real order.
            ->navigationGroups([
                NavigationGroup::make('Sales'),
                NavigationGroup::make('Billing'),
                NavigationGroup::make('Procurement'),
                NavigationGroup::make('Delivery'),
                NavigationGroup::make('Clients'),
                NavigationGroup::make('Catalog'),
                NavigationGroup::make('Expenses'),
                NavigationGroup::make('Documents'),
                NavigationGroup::make('Reports'),
                NavigationGroup::make('Team'),
                NavigationGroup::make('Settings'),
            ])
            // Each Company row is a billed entity (KapturInvoice runs at
            // least two); a user can belong to more than one and switches
            // via the tenant menu. See docs/invoiceninja-v4-schema-reference.md §4.
            ->tenant(Company::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterCompany::class)
            ->tenantProfile(EditCompanyProfile::class)
            // Persistent so it also runs on tenant-scoped Livewire
            // component requests, not just the initial page load.
            ->tenantMiddleware([ApplyCompanyBrand::class], isPersistent: true)
            // The company's own logo (falls back to the panel/brand name
            // when there's none) — Storage::url() doesn't work for the
            // `local` disk, hence the base64 data URI (same reason
            // Company::getLogoDataUri() exists for PDFs).
            ->brandName(fn () => Filament::getTenant()?->name ?? 'KapturInvoice')
            ->brandLogo(function () {
                $company = Filament::getTenant();
                $dataUri = $company?->getLogoDataUri();

                if (! $dataUri) {
                    return null;
                }

                return new HtmlString('<img src="'.$dataUri.'" alt="'.e($company->name).'" class="h-7">');
            })
            ->brandLogoHeight('1.75rem')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                // Replaces Filament's stock dashboard with our own (real
                // revenue/pending/overdue stats + trend chart + expiring
                // quotes, not the framework info card) — see
                // docs/filament-admin-layout-design.md §9.
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            // Stitch's dashboard carries no account card at all — user
            // identity already lives in the topbar user menu, so the
            // stock AccountWidget is redundant chrome (see 01-shell-
            // dashboard.md S8).
            ->widgets([])
            // Filament defaults every relation manager rendered on a
            // resource's View page to read-only (Create/Edit/Delete/etc.
            // actions all silently hidden — no error, just an empty
            // header-actions slot) — see
            // Filament\Panel::hasReadOnlyRelationManagersOnResourceViewPagesByDefault().
            // Since creating a record redirects to its View page by
            // default (Filament\Resources\Pages\CreateRecord::getRedirectUrl()
            // prefers `view` over `edit` when both exist), this silently
            // broke the *primary* path for adding invoice/expense line
            // items, client/vendor contacts, and project tasks — all of
            // which live in relation managers on resources that are
            // "relation-manager-heavy" full pages specifically so those
            // relation managers stay interactive (see CLAUDE.md's
            // Modal-based Create/Edit convention). Disabled globally
            // rather than per-relation-manager since every one of them in
            // this app expects to be interactive wherever it's shown.
            ->readOnlyRelationManagersOnResourceViewPagesByDefault(false)
            // Stitch's collapse-to-icons sidebar control.
            ->sidebarCollapsibleOnDesktop()
            // DESIGN.md §1: dark mode is system-controlled
            // (`prefers-color-scheme`) with no manual toggle at launch —
            // keep dark mode itself on, just hide Filament's own
            // user-menu theme switcher.
            ->darkMode()
            ->themeSwitcher(false)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
