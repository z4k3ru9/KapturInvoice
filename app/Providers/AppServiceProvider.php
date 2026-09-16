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
            // tabular-nums added on top of the package's own default
            // 'number' block — without it, the count-up `animated` stat
            // cards (Overdue invoices/Open quotations/Active jobs) visibly
            // jitter in width as each digit's proportional glyph changes
            // during the animation, not just at rest.
            'number' => 'dark:text-dark-300 text-lg font-bold leading-none tabular-nums *:m-0',
            'slots.footer.wrapper' => 'mx-3',
            'slots.footer.text' => 'dark:text-dark-300 p-1 text-[11px] text-gray-600',
            // Matches the real card's own 36px (h-9 w-9) icon square set
            // above ('wrapper.third') — the package's own skeleton default
            // is a 48px (size-12) bar, which read as a visible size jump
            // once the real card swaps in after loading.
            'skeleton.icon' => 'size-9 rounded-lg',
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

        // Standalone <x-icon> usage (a bare icon, not a button/dropdown
        // trigger's own icon slot — those are covered by the scopes above)
        // ALWAYS carries its own `class="h-X w-X ..."` in this app rather
        // than the component's `xs`/`sm`/`md`/… shorthand props, because
        // TallStackUi\Components\Icon\Component::validate() treats the
        // mere presence of a `class` attribute as "handmade" and disables
        // shorthand sizing entirely — and nearly every standalone icon
        // here also needs a color/dark-mode class, which forces `class=`
        // regardless. A `customize()->icon(...)` block can't reach these
        // for the same reason (the block only feeds the shorthand path),
        // so consistency is enforced by convention/audit instead of a
        // scope. Verified against every real usage in resources/views
        // (icon-sizing audit) — the pixel values below are what's
        // actually in use, not aspirational:
        //   - 12px (h-3 w-3):  a stat card's inline trend arrow next to
        //     its text-xs label, and a tiny glyph inside an 11px-text
        //     compact chip/badge (e.g. a payment method tag, a
        //     test-connection result line).
        //   - 14px (h-3.5 w-3.5): a small inline icon next to a
        //     text-xs (12px) label that ISN'T a chip/badge — a status
        //     flag next to a badge, a linked-record glyph, a drag-handle/
        //     reorder-arrow cluster.
        //   - 16px (h-4 w-4, matches Icon::SIZES 'sm'): the default
        //     "inline icon next to text" weight — nav items, header
        //     toolbar controls (search/collapse/logout), a table cell's
        //     small file/info icon.
        //   - 20px (h-5 w-5, matches Icon::SIZES 'md'): a standalone
        //     boolean/status icon filling a table column on its own (no
        //     accompanying text), or a prominent inline icon in a
        //     text-sm banner/alert — the same weight the icon-action/
        //     row-action button icons above use.
        //   - 24px (h-6 w-6, matches Icon::SIZES 'lg') inside a 48px
        //     (h-12 w-12) circle: a section-level empty-state icon.
        //   - 28px (h-7 w-7, matches Icon::SIZES 'xl') inside a 56px
        //     (h-14 w-14) circle: a page-level/"hero" empty-state icon
        //     (e.g. the dashboard's own zero-state) — deliberately
        //     larger than a section empty-state, but at the same 50%
        //     icon-to-circle ratio, not an arbitrary jump.

        // sideBar('separator', 'nav') customization removed here — Phase 2
        // sidebar repair (see resources/views/components/tallstack/app.blade.php)
        // replaced the flat `<x-side-bar.separator>` group-label list with
        // real `<x-side-bar.item>` groups (nested items), which don't use
        // `<x-side-bar.separator>` at all. Confirmed via grep that no other
        // view references it before removing.
        $this->registerSideBarItemCustomization();

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

        $this->registerContentSurfaceDarkModeFix();
        $this->registerActionColorPalette();
        $this->registerTallStackUiGlobals();
    }

    /**
     * Phase 2 sidebar repair — three real, live-verified problems with the
     * package's own `sideBar.item` defaults, all fixed here rather than
     * per-instance (the component is used only from
     * resources/views/components/tallstack/app.blade.php, so this has no
     * blast radius elsewhere):
     *
     * 1. Bigger tap targets: both the leaf item link and the group header
     *    button ship with `p-2` (8px) padding around their icon — bumped
     *    to `p-2.5` (10px) so the collapsed rail's icon squares read as a
     *    clearly bigger click target, matching this shell's other
     *    icon-only controls (the h-9 w-9 header buttons).
     *
     * 2. Contrast: a GROUP header (button) can't take a per-instance
     *    `class` override at all — confirmed by reading the vendor
     *    item.blade.php, its `<button>` never merges `$attributes` (see
     *    app.blade.php's own comment at the sidebar's `@foreach` loop) —
     *    so its default `text-primary-500`/`dark:text-white` colored
     *    EVERY group icon/label the tenant's own brand color all the
     *    time, not just the active one, which is what a prior QA pass
     *    flagged as "icons render as very light gray" (the far more washed
     *    out failure mode once dark mode's cascade bug, fixed below,
     *    compounded it: brand-red text ended up paired with a dark-mode
     *    gray/white swap that never actually applied against a
     *    still-light background). Recolored to a neutral gray, matching
     *    this shell's original flat `<x-side-bar.separator>` design where
     *    group LABELS were always plain gray and only the active LEAF
     *    item took the brand color.
     *
     * 3. Dark mode: `item.state.current` (the ACTIVE leaf item's own
     *    highlight — `dark:bg-dark-700`/`dark:text-white`, applied with no
     *    per-instance override since the active item's own `class` prop is
     *    deliberately left empty in app.blade.php) and the two group
     *    colors above all collide with the SAME cross-stylesheet cascade
     *    bug documented at length on `<body>`'s own `dark:bg-gray-950!` in
     *    app.blade.php: vendor/tallstackui/tallstackui/dist/tallstackui.css
     *    (loaded after app.css) independently compiles unconditional,
     *    non-important `.bg-dark-700`/`.text-white` rules of its own (some
     *    other bundled component uses them without a `dark:` prefix), which
     *    beat this app's own `dark:`-gated versions at equal specificity
     *    regardless of the visitor's actual color-scheme preference.
     *    `!important` on every `dark:` color block below is the same
     *    minimal fix, confirmed against that same compiled file rather than
     *    applied blindly.
     */
    private function registerSideBarItemCustomization(): void
    {
        TallStackUi::customize()->sideBar('item')->block([
            // Leaf item link — padding bump only; its text/icon color is
            // already fully controlled per-instance from app.blade.php
            // (`!text-gray-600 dark:!text-gray-300` on every non-current
            // item), so this class list doesn't need a color fix itself.
            'item.state.base' => 'group flex items-center rounded-md p-2.5 text-sm font-semibold transition-all',
            // The ACTIVE leaf item's own highlight — no per-instance
            // override reaches this one (see class docblock above), so it
            // needs the dark-mode fix directly. `dark:bg-gray-800!`, not
            // the vendor's own `dark:bg-dark-700` — see
            // `registerAppShellDarkModeFix()`'s docblock below for why a
            // package-specific `dark-*` token can't be used here at all,
            // important or not.
            'item.state.current' => 'text-primary-500 bg-primary-50 dark:bg-gray-800! dark:text-white!',
            // Group header button — padding bump + neutral gray (was
            // `text-primary-500 ... dark:text-white`, permanently
            // brand-colored, see docblock point 2) + the same dark-mode
            // fix (point 3). Hover tint left as the vendor's own
            // `dark:hover:bg-dark-600/50` — unlike the STATIC colors this
            // whole block replaces, that one substring is byte-for-byte
            // identical to the vendor's own default, so it's still served
            // by tallstackui.css's own pre-built rule for it and doesn't
            // hit the "can't compile a new dark-* utility" problem below
            // (nothing "new" about it).
            'group.button' => 'text-gray-600! hover:bg-primary-50/50 dark:text-gray-300! dark:hover:bg-dark-600/50 flex w-full items-center rounded-md p-2.5 text-left text-sm font-semibold transition-all cursor-pointer',
            'group.icon.base' => 'text-gray-600! dark:text-gray-300! h-6 w-6 shrink-0',
            // The expand/collapse chevron (only ever visible when the
            // sidebar is NOT railed, i.e. never subject to the collapsed
            // rail's own contrast complaint) — recolored to match the
            // group button/icon above instead of the brand color, and
            // muted one step further (gray-400/500, not -600/-300) since
            // it's a secondary affordance, not the group's own identity.
            'group.icon.collapse.base' => 'text-gray-400! dark:text-gray-500! ml-auto h-4 w-4 shrink-0 transition-all',
            'group.icon.collapse.rotate' => 'text-gray-400! dark:text-gray-500! rotate-180',
            // The floating panel a collapsed (railed) group opens on
            // hover/click — a NEW surface introduced by this same repair
            // (switching from a flat separator list to real groups, see
            // app.blade.php), so it needs the same dark-mode fix rather
            // than shipping a fresh gap. Its own defaults
            // (vendor Component.php's `flyout.wrapper`/`flyout.header`)
            // are the Floating component's base classes plus this
            // component's own header styling, both built from the same
            // `dark-*` package tokens as everything else fixed in this
            // method — same `!important`-plus-standard-gray treatment,
            // values otherwise copied verbatim from the live-rendered
            // default so only the color tokens change.
            'group.flyout.wrapper' => 'dark:bg-gray-900! border-gray-200 dark:border-gray-800! absolute z-50 rounded-lg border bg-white w-60 overflow-hidden',
            'group.flyout.header' => 'dark:bg-gray-900! text-gray-500 dark:text-gray-400! sticky top-0 -mx-2 bg-white px-2 pt-2 pb-1 text-xs font-semibold tracking-wide uppercase',
        ]);

        $this->registerAppShellDarkModeFix();
    }

    /**
     * Phase 2 sidebar repair's dark-mode investigation traced the "sidebar
     * icons go light-gray, sidebar background stays white" combination
     * (an even worse contrast failure than the flat "everything is brand
     * red" bug fixed above) to its actual source: the sidebar RAIL and the
     * top header bar's own background never switch to dark at all, even
     * though `prefers-color-scheme: dark` is genuinely active and their own
     * `dark:bg-dark-800` class is present — while the item/group text
     * colors fixed above DO correctly switch, leaving a
     * light-on-light-container mismatch that reads exactly like the
     * original "icons render as very light gray" complaint.
     *
     * Confirmed live: with `prefers-color-scheme: dark` forced on and
     * `window.matchMedia('(prefers-color-scheme: dark)').matches` verified
     * true, `getComputedStyle()` on the sidebar rail
     * (`desktop.wrapper.second`) and the header bar (`layout.header`'s
     * `wrapper.base`) both still resolved to `rgb(255, 255, 255)` — plain
     * white — despite carrying `dark:bg-dark-800`.
     *
     * Root cause has TWO parts, both confirmed by reading the compiled CSS
     * byte-for-byte rather than guessed:
     *
     * 1. `dark-700`/`dark-800` are the PACKAGE's own custom color tokens
     *    (`--color-dark-700` etc., defined only inside
     *    vendor/tallstackui/tallstackui's OWN theme, never imported into
     *    this app's resources/css/app.css). Tailwind can only generate a
     *    utility for a color it knows about, so ANY class using a
     *    `dark-*` token — including a brand new `!important` variant of
     *    one — can only ever come from tallstackui.css, never from this
     *    app's own compiled app.css, no matter what string
     *    `TallStackUi::customize()` is given (confirmed: appending `!`
     *    to `dark:bg-dark-800` produced a class that appears in neither
     *    compiled stylesheet — app.css can't generate an unknown-token
     *    utility, and tallstackui.css was frozen at package-publish time
     *    with no `!important` variant of it).
     *
     * 2. Worse, tallstackui.css's OWN `dark:` variant is compiled as
     *    `:where(.dark, .dark *)` — a literal `.dark` CSS CLASS on an
     *    ancestor — not `@media (prefers-color-scheme: dark)` at all
     *    (confirmed: zero `prefers-color-scheme` occurrences anywhere in
     *    that 384KB file, vs. exactly one such block in this app's own
     *    app.css). This app never adds a `.dark` class to anything — no
     *    theme toggle, no `data-theme` attribute, nothing — so EVERY
     *    `dark:` utility whose only compiled source is tallstackui.css
     *    (any package-default styling built from a `dark-*` token, per
     *    point 1) is structurally inert here regardless of the visitor's
     *    actual OS color-scheme preference. This is separate from — and
     *    strictly worse than — the ordering/`!important` collision
     *    documented on `<body>`'s own `dark:bg-gray-950!` fix in
     *    app.blade.php, where BOTH stylesheets at least agreed on the
     *    color and the fix was just winning the cascade.
     *
     * The only fix that actually works within this app's own build is
     * avoiding the package's `dark-*` tokens entirely for anything that
     * needs to visibly react to dark mode, in favor of STANDARD Tailwind
     * colors (`gray-*`) that app.css's own media-query-based `dark:`
     * variant CAN compile — `!important` still required for the same
     * cross-stylesheet collision reason as `<body>`. `gray-900`
     * (oklch 21% lightness) and `gray-800` (oklch 27.8%) below are chosen
     * as the closest standard-palette match to the vendor's own
     * `--color-dark-800` (oklch 18.5%) and `--color-dark-700` (oklch
     * 25.3%) respectively — visually near-identical, not an exact
     * token-for-token swap.
     *
     * This same `dark-*`-token pattern is the package's default for EVERY
     * `<x-card>`, every dropdown panel, and other chrome across the whole
     * admin panel — not just this sidebar/header — confirmed via a live
     * DOM sweep that found the identical failure on dashboard cards.
     * Fixing every one of those is real, page-content-wide scope well
     * beyond "sidebar & app shell" (this task's actual boundary) and was
     * deliberately left alone here — flagged back to the dispatching
     * session as a separate follow-up rather than silently expanded into.
     * Only the two wrapper backgrounds that are structurally part of THIS
     * shell (the sidebar rail, the header bar) are fixed here.
     */
    private function registerAppShellDarkModeFix(): void
    {
        TallStackUi::customize()->sideBar()->block([
            'desktop.wrapper.second' => 'dark:bg-gray-900! dark:border-gray-800! flex grow flex-col border-r border-gray-200 bg-white pb-4 transition-[width] duration-300',
            'mobile.wrapper.fourth' => 'dark:bg-gray-900! flex grow flex-col bg-white pb-4',
        ]);

        TallStackUi::customize()->layout('header')->block([
            'wrapper.base' => 'dark:bg-gray-900! dark:border-gray-800! sticky top-0 z-40 flex shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 sm:gap-x-6 sm:px-6 lg:px-8 tsui-scrollbar-bleed',
        ]);
    }

    /**
     * The follow-up flagged at the end of `registerAppShellDarkModeFix()`'s
     * docblock: the identical "package's own `dark-*` token can only ever be
     * compiled by tallstackui.css, whose `dark:` variant is `.dark`-class-gated
     * rather than `prefers-color-scheme`-gated" defect — confirmed there on
     * `<x-card>` and every dropdown panel via a live DOM sweep — is actually
     * the DEFAULT styling of essentially every TallStackUI component, because
     * they're all built from the same `dark-*` palette. This method is the
     * "audit app-wide" follow-up: every component actually used somewhere in
     * `resources/views` (confirmed via `grep -rohE '<x-[a-z...]' resources/views`
     * against each `Component.php`'s own `customization()`) that carries a
     * `dark:`-prefixed `dark-*`-token class gets the same fix here, using the
     * same two-part recipe as above (swap the token for the closest standard
     * Tailwind `gray-*` shade, add `!important` for the cross-stylesheet
     * ordering collision) — nothing here is a new kind of fix, just the same
     * one applied everywhere the bug actually lives.
     *
     * The gray-shade mapping below is precise, not eyeballed: the package's
     * `dark-*` scale (vendor/tallstackui/tallstackui/css/v4.css) is pure
     * grayscale (`oklch(L 0 0)`) at every step, and standard Tailwind v4
     * `gray-*` is also documented at fixed `oklch` lightness values, so each
     * `dark-N` was mapped to whichever `gray-M` has the closest `L`:
     *
     * | `dark-*` | L (oklch) | closest `gray-*` | L (oklch) |
     * |----------|-----------|-------------------|-----------|
     * | 50       | .985      | 50                | .985      |
     * | 100      | .970      | 100               | .967      |
     * | 200      | .922      | 200               | .928      |
     * | 300      | .870      | 300               | .872      |
     * | 400      | .683      | 400               | .707      |
     * | 500      | .510      | 500                | .551      |
     * | 600      | .360      | 700 (.373 beats 600's .446) | .373 |
     * | 700      | .253      | 800 (.278 beats 700's .373) | .278 |
     * | 800      | .185      | 900 (.210 beats 800's .278) | .210 |
     * | 900      | .145      | 950 (.130 beats 900's .210) | .130 |
     * | 950      | .110      | 950 (nothing standard is darker) | .130 |
     *
     * This table is exactly what `registerSideBarItemCustomization()` already
     * used ad hoc for `dark-800`→`gray-900`/`dark-700`→`gray-800` — it's
     * generalized here rather than re-derived per component.
     *
     * What this method does NOT cover, confirmed present but deliberately
     * left for a separate pass (each is either far deeper/more repetitive
     * than the chrome fixed here, or genuinely low-traffic):
     * - `<x-editor>`'s own rendered CONTENT typography (code blocks,
     *   blockquotes, the `<hr>` rule — `editable.typography.*`) — only its
     *   toolbar/footer/editable-area/image-upload chrome is fixed below.
     * - The internal calendar/swatch/suggestion-row colors of `<x-date>`,
     *   `<x-select.styled>` (Form\Select\Styled — NOT the same as
     *   `<x-select.styled>`'s own PANEL, which reuses the shared
     *   `floating()` fix below), the Form `autocomplete`/`tag`/`color`/
     *   `upload`/`upload.async`/`pin`/`range` components, and
     *   `<x-command-palette>` — the shared `floating()->block('wrapper')`
     *   fix below already fixes their outer panel/popover BACKGROUND (the
     *   same structural bug as the dropdown panel), but the internal
     *   button/row/text colors inside each of those popovers still carry
     *   their own un-migrated `dark-*` classes.
     * - `<x-step>`'s `circles`/`simple` variants — only `panels` (the only
     *   variant actually used, the dashboard setup checklist) is fixed.
     * - `<x-toast>` — NOT fixed here because it didn't need it: this app
     *   enables `globals()->colorful(toast: true)` (see
     *   `registerTallStackUiGlobals()`), which replaces the toast body's
     *   background with a solid per-type color (`colors['background'][type]`)
     *   rather than relying on the package's plain `wrapper.third` (still
     *   `dark:bg-dark-800` underneath, unfixed) — confirmed by reading
     *   `ts-ui::components.toast.main`'s own Blade source rather than
     *   assumed, and spot-checked live in both color schemes.
     */
    private function registerContentSurfaceDarkModeFix(): void
    {
        // Shared by EVERY floating popover in this app — the dropdown panel
        // (<x-dropdown>, both scoped and unscoped), <x-dropdown.submenu>, the
        // <x-select.styled> option list, the date/color/tag/autocomplete
        // pickers, and the password-strength popover — because every one of
        // those components builds its own panel on top of
        // `Floating\Component::customization()['wrapper']` rather than
        // defining its own background (confirmed by reading each of their
        // `Component.php` files: they all resolve
        // `app(Floating::class)->customization()` for their own 'floating'
        // block). One fix here is therefore the single highest-leverage
        // change in this method. `border-dark-200` (light mode, no `dark:`
        // prefix) is left untouched — it's a real, unconditionally-compiled
        // token in tallstackui.css with no cascade collision of its own, so
        // it isn't part of this specific bug.
        TallStackUi::customize()->floating()->block([
            'wrapper' => 'dark:bg-gray-900! border-dark-200 dark:border-gray-800! absolute z-50 rounded-lg border bg-white',
        ]);

        // The dropdown ROW text/icon/divider colors sitting inside that now-
        // fixed panel — <x-dropdown.items> (every row action menu in this
        // app) and <x-dropdown.submenu>.
        TallStackUi::customize()->dropdown('items')->block([
            'item.base' => 'text-gray-600 dark:text-gray-300! dark:hover:bg-gray-800! dark:focus:bg-gray-800! flex w-full cursor-pointer items-center whitespace-nowrap transition-colors duration-150 hover:bg-gray-100 focus:bg-gray-100 focus:outline-hidden',
            'border' => 'dark:border-t-gray-800! border-t border-t-gray-100',
            'icon.base' => 'dark:text-gray-300! text-gray-500',
        ]);
        TallStackUi::customize()->dropdown('submenu')->block([
            'item.base' => 'text-gray-600 dark:hover:bg-gray-800! dark:text-gray-300! dark:focus:bg-gray-800! flex w-full cursor-pointer items-center justify-between whitespace-nowrap transition-colors duration-150 hover:bg-gray-100 focus:bg-gray-100 focus:outline-hidden',
            'border' => 'dark:border-t-gray-800! border-t border-t-gray-100',
        ]);
        // The trigger's own text (e.g. the "toolbar"-scoped dropdowns already
        // fixed in registerTallStackUiCustomizations() override this per
        // scope, but an unscoped/default <x-dropdown> falls back to this).
        TallStackUi::customize()->dropdown()->block([
            'action.text' => 'text-sm text-gray-700 font-medium dark:text-gray-400!',
        ]);

        // <x-card> — the confirmed bug this whole method exists to close.
        // 'header.wrapper.base'/'header.wrapper.border' are already fixed
        // above (registerTallStackUiCustomizations()) with a brand-color
        // wash instead of a dark-* swap; every other surface of the card
        // gets the plain token swap.
        TallStackUi::customize()->card()->block([
            'wrapper.second' => 'dark:bg-gray-900! flex w-full flex-col overflow-hidden bg-white shadow-md',
            'bordered' => 'border border-gray-200 dark:border-gray-800!',
            'header.text.color' => 'text-gray-700 dark:text-gray-300!',
            'body' => 'text-gray-700 dark:text-gray-300! grow px-4 py-5',
            'footer.wrapper' => 'text-gray-700 dark:text-gray-300! dark:border-t-gray-700/50! border-t border-t-gray-200 p-4',
            'loading.overlay' => 'absolute inset-0 z-10 cursor-not-allowed rounded-lg bg-white/50 dark:bg-gray-800/50!',
        ]);

        // <x-modal> — explicitly in scope per the follow-up task ("also
        // check modals"). Every one of this app's ~24 modal-based create/
        // edit flows (see "Modal-based vs. dedicated create/edit" in
        // CLAUDE.md) renders through this one component.
        TallStackUi::customize()->modal()->block([
            'wrapper.first' => 'fixed inset-0 bg-gray-400/75 dark:bg-gray-700/75! transform transition-opacity',
            'wrapper.fourth' => 'dark:bg-gray-900! relative flex w-full transform flex-col rounded-t-xl sm:rounded-xl bg-white text-left shadow-xl transition-all',
            'handle.bar' => 'h-1 w-10 rounded-full bg-gray-300 dark:bg-gray-500!',
            'title.wrapper' => 'dark:border-b-gray-800! flex items-center justify-between border-b border-b-gray-100 px-4 py-2.5',
            'title.text' => 'text-md text-gray-600 dark:text-gray-300! whitespace-normal font-medium',
            'body' => 'dark:text-gray-300! grow rounded-b-xl py-5 text-gray-700 px-4',
            'footer.wrapper' => 'dark:text-gray-300! dark:border-t-gray-800! rounded-b-xl border-t border-t-gray-100 p-4 text-gray-700',
            'footer.scrollable' => 'sticky bottom-0 z-10 bg-white dark:bg-gray-900!',
        ]);

        // <x-slide> — the one slide-over in this app.
        TallStackUi::customize()->slide()->block([
            'wrapper.first' => 'fixed inset-0 bg-gray-400/75 dark:bg-gray-700/75! transform transition-opacity',
            'wrapper.fifth' => 'flex flex-col bg-white py-6 shadow-xl dark:bg-gray-800!',
            'title.text' => 'whitespace-normal font-medium text-md text-gray-600 dark:text-gray-300!',
            'body' => 'soft-scrollbar dark:text-gray-300! grow overflow-y-auto rounded-b-xl px-6 py-5 text-gray-700',
            'footer.wrapper' => 'border-t border-t-gray-200 px-4 pt-4 dark:border-t-gray-700!',
            'header.divider' => 'border-b border-b-gray-200 pb-4 dark:border-b-gray-700!',
        ]);

        // <x-table> — every list page in this app (36 files).
        TallStackUi::customize()->table()->block([
            'wrapper' => 'overflow-hidden dark:ring-gray-800! rounded-lg ring-1 ring-gray-200',
            'table.wrapper' => 'relative soft-scrollbar overflow-auto bg-white dark:bg-gray-900!',
            'table.base' => 'dark:divide-gray-700/50! min-w-full divide-y divide-gray-200',
            'table.th' => 'dark:text-gray-200! px-3 py-3.5 text-sm font-semibold text-gray-700',
            'table.th-compact' => 'dark:text-gray-200! px-3 py-2 text-sm font-semibold text-gray-700',
            'table.tbody' => 'dark:bg-gray-900! dark:divide-gray-500/20! divide-y divide-gray-200 bg-white',
            'table.td' => 'dark:text-gray-300! whitespace-nowrap px-3 py-4 text-sm text-gray-500',
            'table.td-compact' => 'dark:text-gray-300! whitespace-nowrap px-3 py-2.5 text-sm text-gray-500',
            'table.thead.normal' => 'bg-gray-50 dark:bg-gray-950!',
            'table.thead.striped' => 'bg-white dark:bg-gray-950!',
            'row.striped' => 'bg-gray-50 dark:bg-gray-900/50!',
            'loading.icon' => 'text-primary-500 dark:text-gray-300! absolute bottom-0 left-0 right-0 top-0 m-auto grid h-10 w-10 animate-spin place-items-center',
            'loading.indicator' => 'text-primary-500 dark:text-gray-300! absolute inset-0 m-auto grid place-items-center',
            'empty' => 'dark:text-gray-300! col-span-full whitespace-nowrap px-3 py-4 text-sm text-gray-500',
            'empty-compact' => 'dark:text-gray-300! col-span-full whitespace-nowrap px-3 py-2.5 text-sm text-gray-500',
            'slots.header' => 'mb-2 dark:text-gray-300! text-gray-500',
            'slots.footer' => 'mt-2 dark:text-gray-300! text-gray-500',
            'expandable.wrapper' => 'bg-gray-50 dark:bg-gray-900!',
            'expandable.button' => 'text-gray-500 dark:text-gray-300! hover:text-gray-700 dark:hover:text-gray-100! transition-transform duration-200',
        ]);

        // <x-tab> — 5 usages (e.g. the Settings lookups tabbed page).
        TallStackUi::customize()->tab()->block([
            'base.wrapper' => 'dark:bg-gray-900! w-full rounded-lg bg-white shadow-md',
            'base.body' => 'soft-scrollbar flex-nowrap overflow-auto flex bg-gray-50 dark:bg-gray-950! rounded-t-lg',
            'base.content' => 'text-gray-700 dark:text-gray-300! p-4',
            'base.divider' => 'h-px border-0 bg-gray-200 dark:bg-gray-800!',
            'base.select' => 'focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-900! dark:border-gray-800! w-full rounded-lg border-gray-200 px-4 py-3 dark:text-gray-400! sm:hidden',
            'bordered' => 'border border-gray-200 dark:border-gray-800!',
            'item.select' => 'text-primary-500 dark:text-gray-300! border-primary-500 dark:border-gray-300! group inline-flex cursor-pointer items-center border-b-2 font-medium',
            'item.unselect' => 'dark:text-gray-500! cursor-pointer border-b-2 border-transparent font-medium text-gray-400 flex',
        ]);

        // <x-accordion>/<x-accordion.items> — 1 usage.
        TallStackUi::customize()->accordion()->block([
            'wrapper.base' => 'dark:bg-gray-900! w-full overflow-hidden rounded-lg bg-white shadow-md',
            'bordered' => 'border border-gray-200 dark:border-gray-800!',
        ]);
        TallStackUi::customize()->accordion('items')->block([
            'item.wrapper' => 'border-b border-gray-200 dark:border-gray-800! last:border-b-0',
            'item.trigger.base' => 'flex w-full cursor-pointer items-center gap-3 px-4 py-3 text-start text-sm font-medium justify-between transition-colors hover:bg-gray-50 dark:hover:bg-gray-800!',
            'item.trigger.closed' => 'text-gray-700 dark:text-gray-300!',
            'item.content' => 'px-4 pb-4 text-sm text-gray-600 dark:text-gray-400!',
        ]);

        // <x-list>/<x-list.items> — 4 usages.
        TallStackUi::customize()->list()->block([
            'box' => 'dark:bg-gray-900! dark:border-gray-800! rounded-md border border-gray-200 bg-white',
            'search.wrapper' => 'dark:border-gray-800! relative flex h-11 items-center border-b border-gray-200',
            'search.wrapper-compact' => 'dark:border-gray-700! relative flex h-9 items-center border-b border-gray-200',
            'search.icon.wrapper' => 'pointer-events-none absolute left-3 flex size-5 items-center justify-center text-gray-400 dark:text-gray-400!',
            'search.input' => 'h-full w-full border-0 bg-transparent pl-10 pr-3 text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-0 dark:text-gray-100! dark:placeholder:text-gray-400!',
            'items.wrapper' => '[&>[data-list-on]~[data-list-on]]:border-t [&>[data-list-on]~[data-list-on]]:border-gray-200 dark:[&>[data-list-on]~[data-list-on]]:border-gray-800!',
            'empty.text' => 'text-sm text-gray-500 dark:text-gray-400!',
        ]);
        TallStackUi::customize()->list('items')->block([
            'name' => 'text-sm font-medium text-gray-700 dark:text-gray-100!',
            'caption' => 'text-xs text-gray-500 dark:text-gray-400!',
            'menu.trigger' => 'dark:text-gray-400! dark:hover:bg-gray-800! dark:hover:text-gray-200! flex cursor-pointer items-center justify-center rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none',
            'menu.floating' => 'dark:bg-gray-800! dark:border-gray-700! absolute z-40 overflow-hidden rounded-md border border-gray-200 bg-white',
        ]);

        // <x-loading> — the full-screen Livewire loading overlay.
        TallStackUi::customize()->loading()->block([
            'wrapper.first' => 'fixed inset-0 bg-gray-300 dark:bg-gray-950!',
            'opacity' => 'bg-gray-300/75 dark:bg-gray-950/70!',
        ]);

        // <x-avatar>'s presence dot ring — 2 usages.
        TallStackUi::customize()->avatar()->block([
            'presence.dot' => 'rounded-full ring-2 ring-white dark:ring-gray-800!',
        ]);

        // <x-chart> — the dashboard's revenue trend chart, the one Chart
        // usage in this app.
        TallStackUi::customize()->chart()->block([
            'plot.slice' => 'stroke-white dark:stroke-gray-800! stroke-[0.5]',
            'plot.marker' => 'dark:ring-gray-800! absolute size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-current ring-2 ring-white',
            'plot.grid' => 'stroke-gray-200 dark:stroke-gray-700! stroke-[0.4]',
            'plot.crosshair' => 'stroke-gray-300 dark:stroke-gray-500! stroke-[0.5]',
            'axis.y.label' => 'dark:text-gray-400! absolute right-2 -translate-y-1/2 whitespace-nowrap text-[0.65rem] leading-none text-gray-500',
            'axis.y.right.label' => 'dark:text-gray-400! absolute left-2 -translate-y-1/2 whitespace-nowrap text-[0.65rem] leading-none text-gray-500',
            'axis.x.label' => 'dark:text-gray-400! absolute -translate-x-1/2 whitespace-nowrap text-[0.65rem] leading-none text-gray-500',
            'legend.text' => 'dark:text-gray-300! text-gray-600',
            'tooltip.wrapper' => 'dark:bg-gray-950! dark:ring-gray-800! pointer-events-none absolute z-10 rounded-md bg-white px-2.5 py-1.5 ring-1 ring-gray-200',
            'tooltip.title' => 'dark:text-gray-200! mb-1 text-xs font-medium text-gray-700',
            'tooltip.name' => 'dark:text-gray-400! text-gray-500',
            'tooltip.value' => 'dark:text-gray-200! ml-auto pl-2 font-medium text-gray-700',
            'slots.header' => 'dark:text-gray-300! text-sm font-medium text-gray-700',
            'slots.footer' => 'dark:text-gray-400! text-xs text-gray-500',
            'skeleton.fill' => 'fill-gray-200 dark:fill-gray-700!',
            'skeleton.stroke' => 'fill-none stroke-gray-200 stroke-2 dark:stroke-gray-700!',
        ]);

        // <x-step panels>'s panel/circle/divider/text colors — the
        // dashboard's setup checklist, the only <x-step> usage in this app
        // and the only variant (`panels`) it uses. The `circles`/`simple`
        // variants are left unfixed (see this method's own docblock).
        TallStackUi::customize()->step()->block([
            'panels.li' => 'relative md:flex md:flex-1 border-b last:border-b-0 border-gray-200 dark:border-gray-700! md:border-0',
            'panels.circle.inactive' => 'border-2 border-gray-300 dark:border-gray-800!',
            'panels.divider.svg' => 'h-full w-full text-gray-200 dark:text-gray-800!',
            'panels.text.number.active' => 'text-gray-500 dark:text-gray-300!',
            'panels.text.title.inactive' => 'text-gray-600 dark:text-gray-300!',
            'panels.text.description' => 'ml-4 whitespace-nowrap text-xs font-medium text-gray-500 dark:text-gray-400!',
            'panels-shape' => 'mb-2 rounded-md border border-gray-200 dark:border-gray-800!',
        ]);

        // <x-signature> — the client-portal e-signing pad (invoice/
        // quotation/delivery-order/handover-report portal pages).
        TallStackUi::customize()->signature()->block([
            'wrapper.first' => 'dark:bg-gray-900! dark:border-gray-800! rounded-lg border border-gray-200 bg-white',
            'wrapper.second' => 'dark:border-gray-800! flex items-center justify-between space-x-4 border-b border-gray-200 px-4 py-2',
            'canvas.base' => 'dark:border-gray-700! w-full rounded-lg border border-dashed border-gray-300',
            'icons' => 'dark:text-gray-400! h-5 w-5 text-gray-500',
        ]);

        // <x-editor> — 16 usages (invoice/quote notes, proposal content).
        // Toolbar/footer/editable-area/image-upload chrome only — the
        // rendered content's own typography colors are deliberately left
        // unfixed (see this method's own docblock).
        TallStackUi::customize()->editor()->block([
            'wrapper.base' => 'dark:border-gray-800! dark:bg-gray-900! flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white',
            'toolbar.wrapper' => 'dark:border-gray-800! dark:bg-gray-950! flex items-center gap-x-1 overflow-x-auto border-b border-gray-200 bg-gray-50 px-2 py-1.5 soft-scrollbar',
            'toolbar.divider' => 'dark:bg-gray-800! mx-1 h-5 w-px shrink-0 bg-gray-200',
            'toolbar.button.base' => 'dark:text-gray-300! dark:hover:bg-gray-800! focus-visible:ring-primary-500 inline-flex h-8 min-w-8 shrink-0 cursor-pointer items-center justify-center rounded px-2 text-sm text-gray-600 transition outline-none hover:bg-gray-200 focus-visible:ring-2 focus-visible:ring-inset',
            'toolbar.dropdown.trigger' => 'dark:text-gray-300! dark:hover:bg-gray-800! focus-visible:ring-primary-500 inline-flex h-8 shrink-0 cursor-pointer items-center gap-x-1 rounded px-2 text-sm whitespace-nowrap text-gray-600 transition outline-none hover:bg-gray-200 focus-visible:ring-2 focus-visible:ring-inset',
            'editable.content' => 'dark:text-gray-200! relative w-full px-4 py-3 text-sm text-gray-700 outline-none focus:outline-none',
            'editable.placeholder' => 'dark:data-[empty=true]:before:text-gray-500! data-[empty=true]:before:pointer-events-none data-[empty=true]:before:absolute data-[empty=true]:before:text-gray-400 data-[empty=true]:before:content-[attr(data-placeholder)]',
            'footer.wrapper' => 'dark:border-gray-800! dark:bg-gray-950! dark:text-gray-400! flex items-center justify-end gap-x-3 border-t border-gray-200 bg-gray-50 px-3 py-1.5 text-xs text-gray-500',
            'image.upload.area' => 'dark:border-gray-700! dark:text-gray-400! hover:border-primary-400 hover:text-primary-600 flex cursor-pointer flex-col items-center justify-center gap-y-2 rounded-md border-2 border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 transition',
            'image.upload.button' => 'inline-flex items-center gap-x-1.5 text-sm text-gray-600 dark:text-gray-100! font-medium',
            'image.upload.hint' => 'dark:text-gray-400! text-xs text-gray-600',
            'image.upload.progress.wrapper' => 'dark:bg-gray-900! mt-2 h-1 w-full overflow-hidden rounded bg-gray-200',
            'image.preview' => 'dark:border-gray-800! max-h-48 w-full rounded border border-gray-200 object-contain',
        ]);

        // The SINGLE highest-leverage fix in this whole method: every plain
        // text/number/textarea/tag field's actual background/border/text
        // color comes from one shared trait,
        // `TallStackUi\Components\Traits\FormDefaultInputClasses::input()`
        // (`'color' => ['base' => '...dark:text-dark-300...', 'background'
        // => 'dark:bg-dark-800 bg-white', 'disabled' => 'dark:bg-dark-900
        // bg-gray-100']`), spread into EVERY one of these components' own
        // `customization()` under an `input.color.*` key — so this is what
        // was actually behind the live-reported "no search field or filter
        // follows dark mode, at all" symptom: every text input, textarea,
        // number field, and tag input in this app kept its plain white
        // background and gray-600 text under dark mode. Each component
        // below still needs its OWN separate ->form(...)->block() call
        // (the trait is copy-pasted into each class's own customization()
        // array at class-definition time, not shared at runtime), but the
        // fix itself is identical everywhere: the same `input.color.base`/
        // `input.color.background`/`input.color.disabled` keys, gray-swapped.
        $sharedInputColor = [
            'input.color.base' => 'dark:ring-gray-700/50! dark:text-gray-300! text-gray-600 ring-gray-200',
            'input.color.background' => 'dark:bg-gray-900! bg-white',
            'input.color.disabled' => 'dark:bg-gray-950! bg-gray-100',
        ];

        TallStackUi::customize()->form('input')->block($sharedInputColor);
        TallStackUi::customize()->form('input.select')->block($sharedInputColor);
        TallStackUi::customize()->form('number')->block($sharedInputColor);
        TallStackUi::customize()->form('textarea')->block($sharedInputColor);
        // Form\Tag keeps its own hand-written 'input.base' (excluded from
        // the trait spread — see that class's own customization()) but
        // still inherits 'input.color.*' from the same trait, so the same
        // three keys apply here too.
        TallStackUi::customize()->form('tag')->block($sharedInputColor);

        // The icon/clearable-button colors sitting inside those same
        // fields — shared by <x-input icon="...">/<x-input.select
        // icon="...">, both built from the same trait-adjacent pattern.
        TallStackUi::customize()->form('input')->block([
            'icon.wrapper' => 'pointer-events-none absolute inset-y-0 flex items-center text-gray-500 dark:text-gray-400!',
            'icon.color' => 'text-gray-500 dark:text-gray-400!',
            'clearable.wrapper' => 'cursor-pointer absolute inset-y-0 flex items-center text-gray-500 dark:text-gray-400!',
        ]);
        TallStackUi::customize()->form('input.select')->block([
            'icon.wrapper' => 'pointer-events-none absolute inset-y-0 flex items-center text-gray-500 dark:text-gray-400!',
            'icon.color' => 'text-gray-500 dark:text-gray-400!',
            'clearable.wrapper' => 'cursor-pointer absolute inset-y-0 flex items-center text-gray-500 dark:text-gray-400!',
        ]);
        TallStackUi::customize()->form('number')->block([
            'buttons.left.color' => 'dark:text-gray-400! text-gray-500',
            'buttons.right.color' => 'dark:text-gray-400! text-gray-500',
            'buttons.right.separator' => 'border-l border-gray-200 dark:border-gray-700!',
        ]);
        TallStackUi::customize()->form('textarea')->block([
            'count.base' => 'dark:text-gray-400! absolute right-0 mt-1 text-sm text-gray-500',
        ]);
        TallStackUi::customize()->form('tag')->block([
            'label.base' => 'inline-flex h-6 items-center rounded-lg bg-gray-100 px-1 text-sm font-medium text-gray-600 ring-1 ring-inset ring-gray-200 space-x-1 dark:text-gray-100! dark:bg-gray-800! dark:ring-gray-700!',
            'button.wrapper' => 'text-gray-500 dark:text-gray-400! absolute inset-y-0 right-2 flex cursor-pointer items-center',
            'box.item' => 'dark:text-gray-300! dark:hover:bg-gray-600! relative cursor-pointer select-none truncate px-3 py-2 text-gray-700 hover:bg-gray-100',
            'box.highlighted' => 'bg-gray-100 dark:bg-gray-600!',
            'box.empty' => 'block w-full px-3 py-2 text-sm text-gray-600 dark:text-gray-300!',
            'box.after' => 'dark:border-gray-700! border-t border-gray-200',
        ]);

        // <x-select.styled> — 20 usages, this app's main styled dropdown/
        // filter component (its own bespoke structure, not built from the
        // shared trait above, so it needs its own full token swap).
        TallStackUi::customize()->select('styled')->block([
            'input.wrapper.base' => 'dark:text-gray-300! dark:bg-gray-900! dark:focus:ring-primary-600 dark:disabled:bg-gray-950! dark:disabled:ring-gray-700/50! dark:ring-gray-700/50! flex w-full cursor-pointer items-center gap-x-2 rounded-md border-0 bg-white py-1.5 text-sm ring-1 ring-gray-200 disabled:bg-gray-100 disabled:text-gray-500 disabled:ring-gray-200',
            'buttons.base' => 'dark:text-gray-400! text-gray-500 hover:text-red-500 dark:hover:text-red-500',
            'box.button.icon' => 'dark:text-gray-400! h-5 w-5 text-gray-500 hover:text-red-500',
            'box.list.loading.class' => 'text-primary-600 dark:text-gray-400! h-12 w-12 animate-spin',
            'box.list.grouped.wrapper' => 'my-1 ml-2 text-gray-700 dark:text-gray-300! font-semibold',
            'box.list.item.wrapper' => 'dark:text-gray-300! dark:hover:bg-gray-800! dark:focus:bg-gray-800! relative cursor-pointer select-none px-3 py-2 text-gray-700 hover:bg-gray-100 focus:bg-gray-100 focus:outline-hidden',
            'box.list.item.disabled' => 'dark:bg-gray-800! cursor-not-allowed! bg-gray-100',
            'box.list.empty' => 'dark:text-gray-300! block w-full pr-2 text-gray-600',
            'items.placeholder.text' => 'dark:text-gray-400! truncate leading-6 text-gray-400',
            'items.single' => 'dark:text-gray-300! truncate leading-6 text-gray-600',
            'items.multiple.item' => 'dark:text-gray-100! dark:bg-gray-900! dark:ring-gray-800! inline-flex h-6 items-center space-x-1 rounded-lg bg-gray-100 px-2 text-sm font-medium text-gray-600 ring-1 ring-inset ring-gray-200',
        ]);

        // <x-swap> and <x-clipboard> are not used anywhere in this app
        // (confirmed via grep) — no fix registered for either, to avoid
        // maintaining dead customization for components with nothing to
        // verify it against.

        // <x-label> — the field TITLE text rendered above EVERY
        // <x-input>/<x-textarea>/<x-select.styled>/etc. that has a `label`
        // prop, and <x-hint> — the small helper line under a field. Both
        // are their own tiny components (`Form\Label`, `Form\Hint`),
        // composed internally by every other form component rather than
        // duplicated, so one fix each covers every field in the app.
        TallStackUi::customize()->form('label')->block([
            'text' => 'dark:text-gray-300! mb-1 block text-sm font-medium text-gray-600',
        ]);
        TallStackUi::customize()->form('hint')->block([
            'text' => 'dark:text-gray-400! mt-1 block text-sm text-gray-500',
        ]);

        // The label TEXT beside every <x-toggle>/<x-checkbox>/<x-radio> —
        // all three compose the same shared `Wrapper\Radio` component for
        // their own `label` prop, so one fix covers all three.
        TallStackUi::customize()->wrapper('radio')->block([
            'label.text' => 'dark:text-gray-400! cursor-pointer items-center text-sm font-medium text-gray-700',
        ]);

        // <x-toggle>'s own track background, and <x-checkbox>/<x-radio>'s
        // own input background/border.
        TallStackUi::customize()->form('toggle')->block([
            'background.class' => 'bg-gray-200 dark:bg-gray-900! block cursor-pointer rounded-full group-focus:ring-0 group-focus:ring-offset-0 peer-focus:ring-0 peer-focus:ring-offset-0',
        ]);
        TallStackUi::customize()->form('checkbox')->block([
            'input.class' => 'form-checkbox dark:border-gray-700/50! border-1 dark:bg-gray-900! rounded border-gray-200 bg-white ring-0 ring-offset-0 focus:ring-0 focus:ring-offset-0',
        ]);
        TallStackUi::customize()->form('radio')->block([
            'input.class' => 'form-radio dark:border-gray-700/50! border-1 dark:bg-gray-900! rounded-full border-gray-200 bg-white ring-0 ring-offset-0 focus:ring-0 focus:ring-offset-0',
        ]);
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
