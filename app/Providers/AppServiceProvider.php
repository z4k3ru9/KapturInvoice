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
     *
     * A "toolbar" x-dropdown scope (`<x-dropdown scope="toolbar">`) gives a
     * dropdown trigger the same 36px (h-9) height and gray-button look as
     * the plain `<x-button color="gray" sm>` controls it sits beside — the
     * package's own dropdown trigger has no size variants at all (no `sm`
     * prop) and renders shorter/unstyled by default, which is what made
     * button rows like "This month / Export summary / refresh" visibly
     * mismatched in height. A "row-action" scope is the same idea for an
     * icon-only (no `text`) overflow-menu trigger in a table row.
     */
    private function registerTallStackUiCustomizations(): void
    {
        // Corner-radius scale, deliberately two-tier, not one flat value:
        // small interactive controls (buttons, badges, inputs, the 36px
        // icon-only action squares) stay at the package's own default
        // rounded-md (6px); larger containers (cards, the stat-card icon
        // box) use rounded-lg (8px). Forcing every button up to 8px was
        // tried and reverted — the SAME pixel radius reads as a crisp,
        // moderate corner on a wide rectangular button but as a rounded
        // "squircle"/pill on a near-square 36x36 icon button, which is
        // more visually inconsistent than the small size difference
        // between rounded-md and rounded-lg ever was. Keep every
        // icon-only square button (icon-action/row-action scopes below)
        // and every plain <x-button>/<x-badge> at the package default —
        // don't add a border.radius override for them.
        TallStackUi::customize()->stats('compact')->block([
            // gap-2, not gap-3: at this card width (~173px content area,
            // minus the 36px icon box), the text column has ~125-129px to
            // work with depending on title length. With gap-3 (12px) two
            // of the five cards' natural title width ("Outstanding
            // balance", and "Total revenue" once its trend arrow moved
            // next to the title) came in a couple pixels over budget —
            // the icon itself doesn't shrink (shrink-0 below), so the
            // *row* overflowed by that couple of pixels and got centered
            // (justify-center) with the overflow split across both ends,
            // nudging those two cards' icons ~2px left of the other
            // three's. gap-2 frees exactly enough room that every title
            // this page uses fits without any card overflowing, so every
            // icon square lands at the same offset from the card edge.
            'wrapper.second' => 'mx-3 flex h-full items-center justify-center gap-2',
            'wrapper.second-no-header' => 'mt-3',
            'wrapper.second-no-footer' => 'mb-3',
            // shrink-0 on both: without it, the icon square is just
            // another flex child and a longer title (e.g. "Outstanding
            // balance") or the increase/decrease trend arrow squeezes it
            // down from 36px to as little as 20px instead of shrinking
            // the text beside it — the cropped/uneven icon boxes reported
            // against the Stitch mockup.
            'wrapper.third' => 'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
            'icon' => 'h-5 w-5 shrink-0',
            'title' => 'dark:text-dark-300 text-xs text-gray-600',
            'number' => 'dark:text-dark-300 text-lg font-bold leading-none *:m-0',
            'slots.footer.wrapper' => 'mx-3',
            'slots.footer.text' => 'dark:text-dark-300 p-1 text-[11px] text-gray-600',
        ]);

        TallStackUi::customize()->dropdown(scope: 'toolbar')->block([
            'action.wrapper' => 'inline-flex h-9 w-full cursor-pointer items-center gap-x-1.5 rounded-md bg-gray-500 px-3 text-gray-50 hover:bg-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600',
            'action.text' => 'text-sm font-medium',
            'action.icon' => 'h-4 w-4 text-gray-50 transition',
        ]);

        // Icon-only variant for a table row's "..." overflow menu, matching
        // the square gray icon buttons it sits next to (e.g. the row's own
        // "Review" x-button icon="eye" square) rather than the wide
        // text-trigger "toolbar" scope above.
        TallStackUi::customize()->dropdown(scope: 'row-action')->block([
            'action.wrapper' => 'inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-md bg-gray-500 text-gray-50 hover:bg-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600',
            'action.icon' => 'h-5 w-5 text-gray-50 transition',
        ]);

        // A "sm" x-button's own icon defaults to just w-3 h-3 (12px) — fine
        // next to a text label, but adrift in a lot of empty space once
        // the button is icon-only and `square` (bell, refresh, a table
        // row's "Review" eye icon: all 36x36 boxes). This scope matches
        // that icon size to the row-action dropdown's 20px kebab above, so
        // every icon-only square action button in this page reads at the
        // same visual weight.
        TallStackUi::customize()->button(scope: 'icon-action')->block([
            'icon.sizes.sm' => 'h-5 w-5',
        ]);

        // The package's default sideBar.separator "line" style uses its own
        // primary (indigo) brand color, clashing with the tenant's own
        // brand-red active-item color and the plain gray group labels the
        // rest of the sidebar uses — restyled to match instead of standing
        // out as a different brand.
        TallStackUi::customize()->sideBar('separator', 'nav')->block([
            'line.border' => 'border-gray-200 dark:border-dark-700 w-full border-t',
            'line.base' => 'dark:bg-dark-800 text-gray-400 bg-white px-3 text-[11px] font-semibold uppercase tracking-wide whitespace-nowrap overflow-hidden transition-all duration-150',
        ]);

        // The package's own default "input.base" class
        // (TallStackUi\Components\Traits\FormDefaultInputClasses::input())
        // carries `py-1.5` but genuinely no horizontal padding at all — a
        // plain <x-input>/<x-textarea> with no icon/prefix/suffix (the
        // vast majority of fields across every TALL-stack page) renders
        // its typed/displayed text flush against the field's left ring
        // border, not just visually tight against it. Confirmed by
        // reading the vendor source, not a customization this app
        // introduced — `input.paddings.left/right` only apply when an
        // `icon` prop is set. <x-select.styled>'s selected-value box has
        // the same gap in its own separate customization array
        // ('input.wrapper.base'). Fixed globally here rather than adding
        // a `class="px-3"` to every one of the ~225 call sites across
        // this session's pages.
        TallStackUi::customize()->form('input')->block('input.base')->append('px-3');
        TallStackUi::customize()->form('textarea')->block('input.base')->append('px-3');
        TallStackUi::customize()->select('styled')->block('input.wrapper.base')->append('px-3');
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
