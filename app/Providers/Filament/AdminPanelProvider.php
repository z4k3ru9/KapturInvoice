<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Tenancy\EditCompanyProfile;
use App\Filament\Pages\Tenancy\RegisterCompany;
use App\Models\Company;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
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
            ->colors([
                'primary' => Color::Amber,
            ])
            // Each Company row is a billed entity (KapturInvoice runs at
            // least two); a user can belong to more than one and switches
            // via the tenant menu. See docs/invoiceninja-v4-schema-reference.md §4.
            ->tenant(Company::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterCompany::class)
            ->tenantProfile(EditCompanyProfile::class)
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
            ->widgets([
                Widgets\AccountWidget::class,
            ])
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
