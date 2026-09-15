<?php

namespace App\Providers;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use TallStackUi\Facades\TallStackUi;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerCompanyRoleGate();
        $this->registerTallStackUiCustomizations();
    }

    /**
     * A denser "compact" x-stats scope (`<x-stats scope="compact">`), used
     * by the TALL-stack dashboard's 5-card overview row so it packs onto
     * one row at more widths instead of the package's default padding/icon
     * size forcing an awkward wrap.
     */
    private function registerTallStackUiCustomizations(): void
    {
        TallStackUi::customize()->stats('compact')->block([
            'wrapper.second' => 'mx-3 flex h-full items-center justify-center gap-3',
            'wrapper.second-no-header' => 'mt-3',
            'wrapper.second-no-footer' => 'mb-3',
            'wrapper.third' => 'flex h-9 w-9 items-center justify-center rounded-lg',
            'icon' => 'h-5 w-5',
            'title' => 'dark:text-dark-300 text-xs text-gray-600',
            'number' => 'dark:text-dark-300 text-lg font-bold leading-none *:m-0',
            'slots.footer.wrapper' => 'mx-3',
            'slots.footer.text' => 'dark:text-dark-300 p-1 text-[11px] text-gray-600',
        ]);
    }

    /**
     * Applies "Auditor is read-only everywhere" and "physical deletion is
     * Owner-only" (docs/rebuild/PRD.md §Roles, docs/rebuild/Specs.md §10)
     * to every model using App\Models\Concerns\BelongsToCompany — not just
     * the ones with a hand-written Policy — so the acceptance criterion in
     * docs/rebuild/specs/01-company-foundation/Specs.md ("No later feature
     * may bypass the ... policy layer ... without relying on hidden UI
     * buttons") holds automatically for every future company-scoped model
     * too, not only the ones that exist today.
     *
     * Gate::before runs ahead of any model-specific Policy for the same
     * ability. Most of these company-scoped models have no dedicated
     * Policy class today, and Laravel denies an ability by default when
     * neither a Policy nor a Gate::define() covers it — so for the five
     * abilities this hook governs, it must return an explicit true/false
     * rather than deferring with null, or "allowed" would silently mean
     * "denied by Laravel's default." A future model-specific Policy that
     * needs finer business-rule gating on one of these same five ability
     * names (not just role) would need to be consulted some other way,
     * since Gate::before short-circuits before any policy runs — no
     * resource needs that yet.
     */
    private function registerCompanyRoleGate(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            $target = $arguments[0] ?? null;
            $modelClass = is_object($target) ? $target::class : $target;

            if (! is_string($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
                return null;
            }

            if (! in_array(BelongsToCompany::class, class_uses_recursive($modelClass), true)) {
                return null;
            }

            if (! in_array($ability, ['create', 'update', 'delete', 'restore', 'forceDelete'], true)) {
                return null;
            }

            if ($user->is_super_admin) {
                return true;
            }

            $tenant = Filament::hasTenancy() ? Filament::getTenant() : null;

            if (! $tenant instanceof Company) {
                return null;
            }

            if ($ability === 'forceDelete') {
                return $user->hasCompanyRole($tenant, CompanyRole::Owner);
            }

            return $user->hasCompanyRole($tenant, ...CompanyRole::mutatingRoles());
        });
    }
}
