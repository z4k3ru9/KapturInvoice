<?php

namespace App\Providers;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
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
        $this->app->singleton(Tenancy::class);
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
        // `icon` prop is set. Fixed globally here rather than adding a
        // `class="px-3"` to every one of the ~225 call sites across this
        // session's pages.
        TallStackUi::customize()->form('input')->block('input.base')->append('px-3');
        TallStackUi::customize()->form('textarea')->block('input.base')->append('px-3');
        // <x-select.styled>'s trigger text sits in a NESTED div
        // ('input.content.wrapper.first', ships with its own `pl-2`)
        // inside the trigger `<button>` ('input.wrapper.base'). An
        // earlier version of this fix appended `px-3` onto
        // 'input.wrapper.base' too, which — because these are two nested
        // boxes, not one element with a losing/winning utility — didn't
        // override the inner `pl-2` but ADDED to it: 12px (button) + 8px
        // (inner div) = 20px of visual left inset, vs. 12px for every
        // plain <x-input>. Confirmed via a real computed-style
        // measurement (Playwright, Products page selects) before fixing.
        // The correct fix touches only the inner div that actually owns
        // the text's left inset, bumping its own `pl-2` straight to
        // `pl-3` to match plain inputs exactly, with nothing appended to
        // the outer button.
        TallStackUi::customize()->select('styled')->block('input.content.wrapper.first')->replace('pl-2', 'pl-3');
        // <x-select.styled>'s own dropdown option rows ('box.list.item.wrapper')
        // already ship with `px-2` (8px) — left alone, the closed trigger's
        // selected-value text (now pl-3/12px, from the block above) sat 4px
        // to the right of that same option's text once the list opened,
        // reading as the dropdown panel being misaligned/"moved" versus the
        // trigger rather than a padding mismatch. Bumped to the same px-3
        // so the open list's text lines up exactly under the closed
        // trigger's text, and both match this app's other px-3 fields.
        TallStackUi::customize()->select('styled')->block('box.list.item.wrapper')->replace('px-2', 'px-3');

        // Gives every <x-card> header a subtle depth cue against its own
        // body — previously both shared the exact same flat background
        // (parent 'wrapper.second' is bg-white/dark:bg-dark-800, and the
        // header itself set no background of its own), so a page with
        // several cards read as a stack of plain boxes with a label typed
        // on top, no visual separation between "this is the section title"
        // and "this is the content." Rather than a fixed accent color, this
        // tints the header with the CURRENT TENANT's own brand color —
        // `--ts-primary` (set inline on <html> per company in
        // components/tallstack/app.blade.php: Karunia Abadi's red,
        // Axen's blue, etc.) — via `color-mix(..., transparent)`. Mixing
        // toward `transparent` (not a literal white/dark hex) means the
        // result is a translucent brand wash that alpha-composites
        // correctly over whatever sits behind it, so ONE class works in
        // both light and dark mode without a separate `dark:` override.
        // The header's outer wrapper has `overflow-hidden` (Card
        // Component.php's 'wrapper.second'), so this tint is automatically
        // clipped to the card's own rounded top corners.
        TallStackUi::customize()->card()->block('header.wrapper.base')->append('bg-[color:color-mix(in_srgb,var(--ts-primary)_8%,transparent)]');
        // The header's own bottom border ('header.wrapper.border') ships
        // as 'dark:border-b-dark-600/50 border-b border-gray-100' — two
        // separate light/dark colors. Replaced wholesale (not just the
        // light half) with a single color-mix-toward-transparent class, at
        // a stronger 20% mix than the background wash so the dividing
        // line itself reads as "brand" — same reasoning as the background
        // tint above: alpha-compositing over an already-tinted (light) or
        // near-black (dark) background needs no separate `dark:` class,
        // and leaving the old `dark:border-b-dark-600/50` in place would
        // otherwise compete with this on the same element in dark mode.
        TallStackUi::customize()->card()->block('header.wrapper.border')->replace(
            'dark:border-b-dark-600/50 border-b border-gray-100',
            'border-b border-[color:color-mix(in_srgb,var(--ts-primary)_20%,transparent)]'
        );

        $this->registerActionColorPalette();
        $this->registerTallStackUiGlobals();
    }

    /**
     * The app's deliberate, named `<x-button color="...">` palette — one
     * semantic role per kind of action, reused everywhere rather than each
     * page picking a color ad hoc (the state this app was actually in
     * before this method existed: `color="blue"` alone covered "New X" nav
     * buttons, "Save", "Send", "Add line item", and plain navigation links,
     * with no way to tell any of those intents apart by color). Every role
     * below maps to a TallStackUI color/style verified from the real
     * vendor source (`vendor/tallstackui/tallstackui/src/Support/Colors/
     * Components/NormalButtonColors.php`, `.../Components/Button/Normal/
     * Component.php`) and the package's own docs
     * (https://tallstackui.com/docs/customization/color) — `solid` (this
     * app's only style in practice) resolves a color name through
     * `data_get($palette, "{$style}.{$color}")`, so `color="gray"` etc.
     * below are the package's own built-in Tailwind palette entries
     * (unmodified), and `color="brand"` is a new entry this app adds via
     * `App\View\Components\TallStackUi\Colors\NormalButtonColors` (see
     * that class's own docblock for exactly how TallStackUI's
     * `#[ColorsThroughOf(...)]` color-personalization mechanism resolves
     * it — a real, documented extension point, not a `scope`/`block()`
     * override standing in for one).
     *
     * | Role                  | `color=`  | Used for                                                                 |
     * |------------------------|-----------|---------------------------------------------------------------------------|
     * | Primary                | `brand`   | The single main create/commit action of the current page or modal (e.g. "New invoice", the invoice form's "Save", a modal's non-Cancel submit button) — the current tenant's own `--ts-primary` brand color (Karunia's red, Axen's blue), never a fixed hex, so "the button that does the main thing" always reads as that company's own identity. |
     * | Neutral / secondary     | `gray`    | Cancel, Back, and any non-primary structural/utility action (Export, Refresh, "Add line item", icon-only row actions like view/edit/download). Already the package default gray — unchanged. |
     * | Success / confirm       | `green`   | A forward, non-destructive state transition that finalizes something (Issue, Verify a payment). Standard "green = approved/confirmed" convention — unchanged from this app's existing usage. |
     * | Destructive             | `red`     | Delete, Void & reissue, Reverse, Remove — anything that ends, cancels, or undoes a record. Standard "red = destructive" convention — unchanged. |
     * | Caution / sensitive     | `amber`   | Non-destructive but sensitive overrides (Test connection, Record/Approve a vendor PO variance) — already this app's existing usage, kept as-is. |
     * | Info / communicate      | `blue`    | Outbound communication (Send/Resend an invoice) and read-only navigation (a dashboard's "View all"/"View" links). Kept as its own role, distinct from Primary, specifically so a toolbar like the invoice form's Issue/Send/Amend/Void row — four buttons that DO sit side by side — never has two of them collapse onto the same hue (Primary reusing the tenant's OWN brand red for Karunia would otherwise land visually on top of the Destructive red two buttons over). |
     *
     * Primary (`brand`) and Destructive (`red`) are the one pair worth
     * flagging explicitly: Karunia Abadi's own brand color IS a red
     * (`#E63934`), so on that tenant a Primary button and a Destructive
     * button are both, unavoidably, "a red button" — the two are never
     * rendered inside the same toolbar/button-group in this app today
     * (verified across every page this palette was applied to), so they
     * are never seen side by side, but a future page that puts a Primary
     * "brand" action directly next to a Destructive "red" one on Karunia's
     * tenant would read as two shades of the same color, not two distinct
     * actions — worth a real design pass (a different style, e.g.
     * `outline`, for one of the two) if that layout ever comes up, rather
     * than assuming the general "different hue families" guidance above
     * always holds.
     */
    private function registerActionColorPalette(): void
    {
        // No block()/customize() call belongs in this method — the actual
        // color mapping lives entirely in
        // App\View\Components\TallStackUi\Colors\NormalButtonColors,
        // TallStackUI's own color-personalization extension point (see its
        // docblock). This method exists purely so the palette's rationale
        // has one documented home next to every other cross-cutting
        // TallStackUI customization in this file, per this file's own
        // established pattern of "explain the WHY right next to the
        // component being customized."
    }

    /**
     * TallStackUI's "globals" preset system
     * (https://tallstackui.com/docs/customization/globals) — a SEPARATE
     * mechanism from the per-component `->form(...)`/`->button(...)`/
     * `->card()` soft-customization calls above: those target one
     * component's own `customization()`/color blocks, while
     * `->globals()` applies one of three sweeping, cross-component
     * presets app-wide: `flash()` (drops every component's `x-transition`
     * directives for instant, non-animated show/hide), `square()` (strips
     * every `rounded-*` class app-wide), and `colorful()` (inverts
     * Dialog/Toast notification styling: the notification-type color
     * becomes the body background with white text, instead of the
     * package's default white card plus a small colored accent icon).
     *
     * Only `colorful()` is enabled here, scoped to `toast` alone
     * (`dialog: false`):
     * - This app's only interactive-notification surface in real use is
     *   `TallStackUi\Traits\Interactions::toast()` — grep confirms every
     *   Livewire component in `app/Livewire/*.php` calls
     *   `$this->toast()->success(...)`/`->error(...)`, and NONE call
     *   `$this->dialog()` or render `<x-dialog>` anywhere in this app.
     *   Destructive confirmations here use Livewire's own native
     *   `wire:confirm="..."` (a plain browser `confirm()` popup, not
     *   TallStackUI's Dialog component) — see e.g. the invoice form's
     *   "Issue"/"Remove this line item?" buttons. `colorful(dialog: true)`
     *   would therefore be dead configuration with nothing in this
     *   codebase to visibly affect; `colorful(toast: true)` immediately
     *   changes real, already-shipping UI across the whole app.
     * - The effect itself is exactly what ties Toast into the SAME
     *   semantic system as the button palette documented above: a
     *   `$this->toast()->success(...)` (paired with a Primary/Success
     *   button action, e.g. "Invoice saved."/"Invoice issued.") now reads
     *   as a bold green card instead of a neutral white one with a small
     *   green icon, and `$this->toast()->error(...)` (paired with a
     *   Destructive-flavored failure, e.g. "Could not issue invoice")
     *   reads as bold red — the toast reinforces the same color meaning
     *   the button that triggered it already carries, rather than being a
     *   visually neutral notification that happens to have a colored
     *   icon.
     *
     * `square()` is deliberately NOT enabled — this file's own
     * `TallStackUi::customize()->stats('compact')->block([...])` call
     * above documents a two-tier rounded-md/rounded-lg corner system
     * that was specifically chosen over one flat radius (see that
     * block's own comment: the same pixel radius reads differently on a
     * wide button vs. a near-square icon button). `square()` strips
     * every `rounded-*` class app-wide unconditionally, which would
     * silently overwrite that already-settled, explicitly-reasoned
     * design decision — out of scope for a color-palette task and not
     * requested.
     *
     * `flash()` is also NOT enabled — it removes Alpine transition
     * animations, an interaction/motion change, not a color one, and
     * nothing about this task calls for it.
     */
    private function registerTallStackUiGlobals(): void
    {
        TallStackUi::customize()->globals()->colorful(toast: true, dialog: false);
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

            $tenant = app(Tenancy::class)->get();

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
