@props(['company', 'active' => null, 'title' => null])

@php
    $primary = $company->primary_color ?: '#E63934';
    $logo = $company->getLogoDataUri();

    // The notification bell's actual content: the SAME role-aware
    // "Action queue" the Dashboard's own ActionQueueWidget already shows
    // (App\Support\Dashboard\ActionQueue, DESIGN.md §3) — reused here
    // rather than a new notifications table, so it stays real, per-role
    // data with zero new persistence. Computed on every shell render
    // (this app's own shell, not a separate Livewire component), the same
    // handful of lightweight COUNT queries the dashboard already pays for.
    $actionQueueItems = auth()->user() ? \App\Support\Dashboard\ActionQueue::for(auth()->user(), $company) : [];
    $actionQueueTotal = collect($actionQueueItems)->sum('count');

    // Segments the flat Action Queue into labeled sections in the bell's
    // dropdown, one per business area — same name/icon/order as this
    // file's own sidebar $nav groups above, so the mental model matches:
    // whichever section a notification sits under is the same nav group
    // its link (below) actually opens. Categories with no current items
    // are simply omitted rather than shown empty.
    $actionQueueCategoryMeta = [
        'Sales' => 'rocket-launch',
        'Billing' => 'banknotes',
        'Procurement' => 'shopping-bag',
        'Delivery' => 'truck',
    ];
    $actionQueueGroups = collect($actionQueueCategoryMeta)
        ->map(fn ($icon, $category) => [
            'label' => $category,
            'icon' => $icon,
            'items' => collect($actionQueueItems)->where('category', $category)->values(),
        ])
        ->filter(fn ($group) => $group['items']->isNotEmpty())
        ->values();

    // Phase 2 sidebar repair (docs/rebuild — see the plan referenced from
    // the dispatching session): each top-level group below now carries its
    // own 'icon' alongside its 'items' array. The group array shape used
    // to be a flat `'Group' => [item, item, ...]`; every `foreach ($nav ..)`
    // consumer downstream was updated in the same pass to read
    // `$definition['icon']`/`$definition['items']` instead of iterating the
    // group's value directly — see the sidebar markup below.
    $nav = [
        'Sales' => [
            'icon' => 'rocket-launch',
            'items' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => route('tallstack.dashboard', $company), 'icon' => 'squares-2x2'],
                ['key' => 'quotations', 'label' => 'Quotations', 'route' => route('tallstack.quotations', $company), 'icon' => 'document-text'],
                ['key' => 'jobs', 'label' => 'Jobs', 'route' => route('tallstack.jobs', $company), 'icon' => 'briefcase'],
            ],
        ],
        'Billing' => [
            'icon' => 'banknotes',
            'items' => [
                ['key' => 'invoices', 'label' => 'Invoices', 'route' => route('tallstack.invoices', $company), 'icon' => 'document-currency-dollar'],
                ['key' => 'recurring-invoices', 'label' => 'Recurring Invoices', 'route' => route('tallstack.recurring-invoices', $company), 'icon' => 'arrow-path'],
                ['key' => 'payments', 'label' => 'Payments', 'route' => route('tallstack.payments', $company), 'icon' => 'credit-card'],
                ['key' => 'quotes', 'label' => 'Quotes', 'route' => route('tallstack.quotes', $company), 'icon' => 'document-duplicate'],
                ['key' => 'credits', 'label' => 'Credits', 'route' => route('tallstack.credits', $company), 'icon' => 'receipt-refund'],
            ],
        ],
        // Mirrors the equivalent resource's own navigationGroup
        // ('Proposals', a group of its own rather than folded into
        // Sales/Billing) from the pre-TallStackUI Filament admin — that
        // resource's own shouldRegisterNavigation() was false so it never
        // actually showed in the Filament sidebar, but this is still the
        // grouping the resource itself declared.
        'Proposals' => [
            'icon' => 'presentation-chart-bar',
            'items' => [
                ['key' => 'proposals', 'label' => 'Proposals', 'route' => route('tallstack.proposals', $company), 'icon' => 'presentation-chart-bar'],
            ],
        ],
        'Procurement' => [
            'icon' => 'shopping-bag',
            'items' => [
                ['key' => 'vendor-bills', 'label' => 'Vendor bills', 'route' => route('tallstack.vendor-bills', $company), 'icon' => 'clipboard-document-list'],
                ['key' => 'vendor-purchase-orders', 'label' => 'Purchase orders', 'route' => route('tallstack.vendor-purchase-orders', $company), 'icon' => 'shopping-cart'],
                ['key' => 'vendors', 'label' => 'Vendors', 'route' => route('tallstack.vendors', $company), 'icon' => 'building-storefront'],
                // Pre-Filament-removal gap audit item
                // (docs/rebuild/outputs/ui-rebuild/27-filament-parity-gap-prompts.md
                // prompt 19) — mirrors the equivalent resource's own
                // navigationGroup ('Procurement') from the pre-TallStackUI
                // Filament admin, folded into this SAME
                // 'Procurement' key, never a second 'Procurement' => [...]
                // block (see this file's own duplicate-key warning further
                // down).
                ['key' => 'expenses', 'label' => 'Expenses', 'route' => route('tallstack.expenses', $company), 'icon' => 'receipt-refund'],
            ],
        ],
        // Phase 7 (docs/rebuild/outputs/ui-rebuild/25-tallstack-full-rebuild-plan.md)
        // — matches AdminPanelProvider's own pinned nav-group order (Sales
        // → Procurement → Delivery → Catalog → Reports → Settings) and the
        // Stitch "Delivery Orders & Handover Register" mockup's own
        // sidebar, which places this group in the same spot.
        'Delivery' => [
            'icon' => 'truck',
            'items' => [
                ['key' => 'delivery-orders', 'label' => 'Delivery Orders', 'route' => route('tallstack.delivery-orders', $company), 'icon' => 'truck'],
                ['key' => 'handover-reports', 'label' => 'Handover Reports', 'route' => route('tallstack.handover-reports', $company), 'icon' => 'document-check'],
            ],
        ],
        'Clients' => [
            'icon' => 'identification',
            'items' => [
                ['key' => 'clients', 'label' => 'Clients', 'route' => route('tallstack.clients', $company), 'icon' => 'user-group'],
                // Pre-Filament-removal gap audit item
                // (docs/rebuild/outputs/ui-rebuild/27-filament-parity-gap-prompts.md
                // prompt 21) — mirrors the equivalent resource's own
                // navigationGroup ('Clients') from the pre-TallStackUI
                // Filament admin, folded into this SAME
                // 'Clients' key, never a second 'Clients' => [...] block (see
                // this file's own duplicate-key warning further down).
                ['key' => 'client-portal-invitations', 'label' => 'Portal Invitations', 'route' => route('tallstack.client-portal-invitations', $company), 'icon' => 'link'],
            ],
        ],
        'Catalog' => [
            'icon' => 'archive-box',
            'items' => [
                ['key' => 'products', 'label' => 'Products', 'route' => route('tallstack.products', $company), 'icon' => 'cube'],
                // Deferred item, built alongside Products/Catalog
                // (docs/rebuild/outputs/ui-rebuild/25-tallstack-full-rebuild-plan.md) —
                // folded into this SAME 'Catalog' key, never a second
                // 'Catalog' => [...] block (see this file's own duplicate-key
                // warning further down).
                ['key' => 'price-list-items', 'label' => 'Price List', 'route' => route('tallstack.price-list-items', $company), 'icon' => 'currency-dollar'],
            ],
        ],
        // Pre-Filament-removal gap audit item (docs/rebuild/outputs/ui-rebuild/27-filament-parity-gap-prompts.md
        // prompt 22) — mirrors the equivalent resource's own
        // navigationGroup ('Documents', a group of its own, placed
        // right after Catalog/before Reports in AdminPanelProvider's own
        // pinned nav-group order) — a genuinely new top-level array entry,
        // never folded into an existing group (see this file's own
        // duplicate-key warning further down for why that distinction
        // matters).
        'Documents' => [
            'icon' => 'paper-clip',
            'items' => [
                ['key' => 'documents', 'label' => 'Documents', 'route' => route('tallstack.documents', $company), 'icon' => 'paper-clip'],
            ],
        ],
        // Phase 10 (docs/rebuild/outputs/ui-rebuild/25-tallstack-full-rebuild-plan.md)
        // — matches AdminPanelProvider's own pinned nav-group order (Sales
        // → Procurement → Delivery → Catalog → Reports → Settings) and the
        // Stitch "Financial Analytics & Tax Reports" mockup's own sidebar,
        // which places this group in the same spot, right before Settings.
        // This key did not exist before this phase, so it's a genuinely
        // new top-level array entry — not a fold into an existing group
        // (see this file's own duplicate-key warning further down for why
        // that distinction matters).
        'Reports' => [
            'icon' => 'chart-bar',
            'items' => [
                ['key' => 'reports-financial', 'label' => 'Financial Analytics', 'route' => route('tallstack.reports', $company), 'icon' => 'chart-bar'],
            ],
        ],
        // Phase 9 (docs/rebuild/outputs/ui-rebuild/25-tallstack-full-rebuild-plan.md)
        // — matches AdminPanelProvider's own pinned nav-group order
        // (... Reports → Settings, last) and the Stitch "Company & Taxes
        // Settings"/"Settings — Tax Rates & Small Lookups" mockups' own
        // sidebar, which both place this group last too.
        // "Users & Roles" is administrative/settings-adjacent (matches the
        // equivalent resource's own navigationGroup 'Team' from the
        // pre-TallStackUI Filament admin — kept under this app's own
        // "Settings" grouping here since a standalone one-item nav group
        // reads as noise at this sidebar width) — folded into the same
        // 'Settings' key as the Phase 9 pages below it. PHP array literals
        // silently let a later duplicate key overwrite an earlier one, so
        // these MUST stay merged into one array, never split into two
        // 'Settings' => [...] entries again.
        'Settings' => [
            'icon' => 'cog-6-tooth',
            'items' => [
                // Phase 12 (F29) settings consolidation — the six former
                // separate nav entries (Company & Taxes/Email & Reminders/
                // Branding/Tax Rates & Lookups/Numbering/Client Portal)
                // collapsed into this ONE item: each is now a tab on the
                // consolidated settings page (resources/views/components/
                // tallstack/settings-tabs.blade.php) rather than its own
                // sidebar row. The six routes/pages/Livewire classes
                // themselves are unchanged — this item links to the first
                // tab, "Company & Taxes". Every one of those six pages now
                // sets `'active' => 'settings'` (see each TallStackSettings*
                // Livewire class's own render()), so this single item stays
                // highlighted no matter which settings tab is open. Users &
                // Roles and Payment Gateways deliberately stay as their own
                // separate nav items/pages below — both are full CRUD
                // register-style resources (member list with role/invite
                // management, gateway list with a Test Connection action),
                // not simple one-row-per-tenant settings forms like the six
                // that were consolidated, so folding them into the same tab
                // strip would misrepresent what they are.
                ['key' => 'settings', 'label' => 'Settings', 'route' => route('tallstack.settings.company-and-taxes', $company), 'icon' => 'cog-6-tooth'],
                ['key' => 'users', 'label' => 'Users & Roles', 'route' => route('tallstack.users', $company), 'icon' => 'user-group'],
                // Pre-Filament-removal audit gap (docs/rebuild/outputs/ui-rebuild/27-filament-parity-gap-prompts.md
                // prompt 20) — folded into this SAME 'Settings' key, never a
                // second 'Settings' => [...] block (see this file's own
                // duplicate-key warning above).
                ['key' => 'payment-gateways', 'label' => 'Payment Gateways', 'route' => route('tallstack.payment-gateways', $company), 'icon' => 'credit-card'],
            ],
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="en" style="--ts-primary: {{ $primary }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dashboard' }} — {{ $company->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { --color-primary-500: {{ $primary }}; }
        /*
            The package's own desktop collapse button (aria-label="Toggle
            sidebar") always renders the same static bars-4 icon whatever
            the sidebar's state and is rendered unconditionally whenever
            the sidebar is collapsible — `without-mobile-button` below
            only drops its separate MOBILE hamburger, not this one. It's
            replaced by the single adaptive button further down (icon
            swaps between hamburger/collapse/expand so the control's
            direction is visible at a glance), so the stock one is hidden
            here rather than forking the vendor view.

            Selector is a bare `button[aria-label=...]`, not
            `header button[...]` — TallStackUI's header component renders
            as a plain <div>, never an actual <header> element, so a
            `header`-qualified selector silently never matches and the
            stock button stayed visible right next to the replacement
            (looking like two redundant nav toggles sitting side by side).

            The search box below still needs its own
            "hidden sm:!block" (`!` important) rather than plain
            "sm:block": TallStackUI's own compiled stylesheet
            (@tallStackUiStyle, loaded after app.css) redeclares a bare
            `.hidden{display:none}` without redeclaring every responsive
            variant this app uses, so its later-loaded rule otherwise wins
            the cascade over an equal-specificity `sm:` utility from
            app.css at any width — the same kind of cross-stylesheet
            Tailwind v4/lightningcss cascade quirk already documented and
            worked around elsewhere in this app.
        */
        button[aria-label="Toggle sidebar"] { display: none !important; }
    </style>
    @livewireStyles
    @tallStackUiStyle
</head>
{{--
    dark:bg-gray-950! (Tailwind v4 trailing-bang `!important`, not the plain
    dark:bg-gray-950 this carried before) — Phase 2 sidebar repair's dark-mode
    investigation traced down WHY dark mode visually never engaged anywhere
    in this shell despite `prefers-color-scheme: dark` genuinely being active
    (confirmed live: `window.matchMedia('(prefers-color-scheme: dark)').matches`
    true, `getComputedStyle(document.body).backgroundColor` still the LIGHT
    gray-50 value) and despite the compiled app.css rule for `dark:bg-gray-950`
    being correctly nested inside a real `@media (prefers-color-scheme: dark)`
    block (brace-matched and confirmed, same check this app's own
    status-badge.blade.php docblock already describes doing for its own
    colors).

    Root cause, confirmed by inspecting BOTH compiled stylesheets byte-for-byte:
    `@tallStackUiStyle` below (vendor/tallstackui/tallstackui/dist/tallstackui.css,
    384KB) is loaded AFTER app.css in <head> (see this file's own comment on
    the hidden `button[aria-label="Toggle sidebar"]` rule above, which already
    documents this same later-wins-the-cascade ordering for a different
    property) — and that vendor stylesheet independently compiles its OWN
    plain, unconditional `.bg-gray-50{background-color:var(--color-gray-50)}`
    rule (used by some vendor component template somewhere in its 80+
    components, unrelated to this app), with ZERO `@media
    (prefers-color-scheme: dark)` blocks anywhere in that file at all. At
    equal specificity (both are single-class selectors), the LATER stylesheet
    in document order wins regardless of whether the earlier rule's own
    `@media` condition is satisfied — CSS doesn't grant extra cascade
    priority for being inside a matched media query. So this app's own
    `dark:bg-gray-950` (app.css, correctly gated, but not `!important`)
    always lost to the vendor's `bg-gray-50` (tallstackui.css, unconditional,
    loaded later), regardless of the visitor's actual color-scheme
    preference — reproducing the exact "structurally correct CSS, still
    renders light" symptom this task was dispatched to investigate.

    `!important` is the correct, minimal fix (not reordering the two
    `<head>` includes, which risks breaking whatever else currently depends
    on tallstackui.css loading last) because `!important` beats a
    non-important rule regardless of source order — the same fix this app's
    status-badge.blade.php already uses for the identical class of bug, and
    now applied here at its actual root cause (a cross-stylesheet collision
    on a bare Tailwind utility class, not something special about `<body>`
    or about this specific color). See `registerSideBarItemCustomization()` in
    AppServiceProvider.php for the same fix applied to the sidebar's own
    item/group colors, which the same root cause affects.
--}}
<body class="antialiased bg-gray-50 dark:bg-gray-950!">

<x-layout>
    <x-slot:menu>
        {{--
            `navigate` (not `navigate-hover`): every item below binds
            `:route`, not `:href` — TallStackUI's own item.blade.php only
            ever attaches `wire:navigate`/`wire:navigate.hover` when
            `$href` is null (vendor/tallstackui/tallstackui/src/resources/
            views/components/layout/sidebar/item.blade.php), so `href`
            would have silently made this a no-op. `navigate-hover`
            (prefetch-on-hover) was deliberately skipped: this app's dev
            server is a single-threaded `php artisan serve`
            (docs/rebuild/specs/06b-ux-browser-soa/... already documents
            this queuing real requests behind each other), so an eager
            hover-prefetch competing with an actual click's request is
            more request-queuing risk than the prefetch is worth; plain
            `navigate` (fires only on an actual click) was verified stable
            across 8+ page-to-page navigations, including 5 rapid clicks
            in a row, in both companies and both color schemes — see this
            audit's report.

            `smart` is deliberately NOT set here — every item's
            `current` is instead computed explicitly per page (each
            TallStack*.php Livewire component passes its own `active`
            key to this layout). `smart`'s own `matches()` (vendor
            Component.php) does an exact current-URL-vs-route-URL string
            compare, which cannot express "this nested edit/detail page
            still highlights its parent list item" (e.g. a Quotation edit
            page must keep "Quotations" active, not go dark) — and one
            page (TallStackInvoiceForm) picks between two DIFFERENT nav
            keys ('quotes' vs 'invoices') from the record's own `type`
            column, not from the route at all. Both are real requirements
            `smart`/`match` cannot express; the explicit per-page `active`
            key was verified correct instead.
        --}}
        <x-side-bar collapsible thin-scroll navigate>
            {{--
                px-4 py-4 (was px-1, no vertical padding at all): the brand
                block used to sit flush against the sidebar's own top-left
                corner — `desktop.wrapper.second`/`.third` (vendor
                main.blade.php) carry no padding of their own, so whatever
                this slot supplies is the only inset the logo/name ever get.
                4px horizontal and zero vertical read as clipped/cramped
                against the h-16 header row; 16px on both axes gives the
                logo chip and text real breathing room while still fitting
                the fixed h-16 brand row.
            --}}
            <x-slot:brand>
                <div class="flex items-center gap-3 px-4 py-4">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="" class="w-8 h-8 rounded-lg object-cover shrink-0">
                    @else
                        <span class="w-8 h-8 rounded-lg grid place-items-center text-white font-bold text-sm shrink-0"
                              style="background: {{ $primary }}">{{ mb_substr($company->name, 0, 1) }}</span>
                    @endif
                    <div class="min-w-0">
                        <div class="font-semibold text-sm text-gray-900 dark:text-gray-100 truncate">{{ $company->name }}</div>
                        <div class="text-[11px] text-gray-400">Enterprise billing</div>
                    </div>
                </div>
            </x-slot:brand>
            {{-- Collapsed rail: logo only, no name — the full name has no
                 room in the railed width and only ever showed truncated.
                 Same px/py bump as the expanded brand slot above, for the
                 same reason (no padding at all otherwise). --}}
            <x-slot:brandCollapsed>
                <div class="flex items-center justify-center px-2 py-4">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="{{ $company->name }}" class="w-8 h-8 rounded-lg object-cover shrink-0">
                    @else
                        <span class="w-8 h-8 rounded-lg grid place-items-center text-white font-bold text-sm shrink-0"
                              style="background: {{ $primary }}">{{ mb_substr($company->name, 0, 1) }}</span>
                    @endif
                </div>
            </x-slot:brandCollapsed>

            {{--
                Phase 2 sidebar repair — each nav group is now a real
                `<x-side-bar.item>` GROUP (nested `<x-side-bar.item>`
                children in its default slot), not a flat list under a
                plain `<x-side-bar.separator>` label. This is TallStackUI's
                own native, purpose-built mechanism for exactly this case,
                confirmed against the bundled package docs
                (vendor/tallstackui/tallstackui/.ai/components/layout/sidebar/{item,main}.md)
                and the component source
                (vendor/tallstackui/tallstackui/src/resources/views/components/layout/sidebar/item.blade.php):
                a group with nested items becomes a collapsible accordion
                section on a full-width sidebar, AND on a `collapsible`
                sidebar's railed (icon-only) state it collapses to a
                single group icon that opens its items in a floating panel
                on hover/click, anchored beside the icon, headed by the
                group's own label. That is a closer fit to this task's
                actual goal ("collapsed sidebar shows ~10 group icons
                instead of ~24 item icons, expand a group to reach its
                items") than wrapping the separate, generic `<x-accordion>`
                component would have been: `<x-accordion>` has no notion of
                the sidebar's own rail/collapse state at all (no
                icon-only/flyout mode, no tooltip, no integration with the
                `$store['tsui.side-bar'].collapsed` Alpine store this shell
                already drives from the header toggle button above), so
                reproducing this exact behavior with it would mean
                re-implementing what the side-bar's own item component
                already does. Kept as a deliberate deviation from the
                dispatching task's literal "use x-accordion" suggestion —
                flagged in this session's own report for the orchestrator
                to double-check.

                A group auto-opens on load when one of its own children is
                the active route: the vendor item.blade.php group wrapper
                seeds its local Alpine `show` state from
                `Str::contains($slot, 'ts-ui-group-opened')`, and a leaf
                item's own rendered `<a>` carries that literal class
                whenever ITS `current` prop is true (see the leaf item's
                own class list further down) — so no manual `opened` prop
                is needed here; the currently active page's group simply
                starts expanded.
            --}}
            {{--
                No per-instance `class` override on the GROUP
                `<x-side-bar.item>` below (unlike the leaf items, which
                each carry one): confirmed by reading the vendor
                item.blade.php that the GROUP branch's `<button>` never
                merges `$attributes` at all (`@class([...])` with no
                `{{ $attributes }}`, unlike the leaf branch's `<a>`, which
                does) — a `class="..."`/`:class="..."` prop passed to a
                GROUP item is silently a no-op, so a per-group "is this
                THE active group" color was structurally impossible to add
                this way. Every group's button/icon color is instead
                recolored once, globally, via
                `registerSideBarItemCustomization()` in
                AppServiceProvider.php — a neutral gray always, matching
                this shell's original flat-separator design (group LABELS
                were always plain gray; only the individual active LEAF
                item ever took the tenant's brand color). The active
                group still reads as "the current section" because it
                auto-opens (see the `ts-ui-group-opened` note above) and
                its own active leaf item is still brand-highlighted once
                visible.
            --}}
            @foreach ($nav as $group => $definition)
                <x-side-bar.item :text="$group" :icon="$definition['icon']">
                    @foreach ($definition['items'] as $item)
                        {{--
                            The component's own default text color is
                            `text-primary-500` on every item, current or not —
                            `!` (important) utilities override it so only the
                            current route reads in the tenant's brand color,
                            matching the Stitch mockup's muted/active contrast.
                        --}}
                        {{--
                            `:route`, not `:href` — see this file's own
                            `navigate` comment above on <x-side-bar>: only
                            `route` lets the vendor item template attach
                            `wire:navigate`. Both props render an identical
                            `href="..."` attribute value either way (vendor
                            Component.php resolves `route ?? href`), and
                            `smart` stays off (see above), so `current` below
                            is still the only thing that decides highlighting
                            — this swap changes no visible behavior beyond
                            enabling SPA navigation.
                        --}}
                        <x-side-bar.item
                            :text="$item['label']"
                            :route="$item['route']"
                            :current="$active === $item['key']"
                            :class="$active === $item['key'] ? '' : '!text-gray-600 dark:!text-gray-300'"
                        >
                            {{--
                                16px (w-4 h-4), matching this shell's other
                                chrome icons (sidebar toggle/search/logout,
                                below) — this app's standard "inline icon next
                                to text" size, not an arbitrary one-off value.
                            --}}
                            <x-slot:icon>
                                <x-icon :name="$item['icon']" class="w-4 h-4" />
                            </x-slot:icon>
                        </x-side-bar.item>
                    @endforeach
                </x-side-bar.item>
            @endforeach
        </x-side-bar>
    </x-slot:menu>

    <x-slot:header>
        <x-layout.header without-mobile-button>
            <x-slot:left>
                {{--
                    A single adaptive toggle rather than two separate icons:
                    the package's own header renders its own mobile
                    hamburger (opens the mobile drawer) alongside our
                    desktop collapse toggle, and since both only ever
                    render one-at-a-time by breakpoint they LOOK like two
                    controls doing the same "open/close the nav" job side
                    by side whenever a viewport briefly sits in between —
                    `without-mobile-button` above drops the package's own
                    one, and this single button below picks the right
                    action (open the mobile drawer vs. collapse the rail)
                    from the live viewport width instead of two elements
                    that both claim to toggle navigation.
                --}}
                <div x-data="{
                        desktop: window.matchMedia('(min-width: 768px)').matches,
                        init() {
                            window.matchMedia('(min-width: 768px)').addEventListener('change', (e) => this.desktop = e.matches);
                        },
                     }">
                    {{--
                        w-8 h-8 to match the sidebar brand logo/avatar
                        square exactly (not the header's other h-9 w-9
                        buttons) — a visible bg (not just on :hover) so it
                        reads as a defined square chip sitting next to that
                        logo, the same way the logo itself is a defined
                        square, rather than a bare icon floating beside it.
                        All three swapped icons share one size so the
                        button's visual weight doesn't shift between
                        states.

                        `dark:text-gray-300!`/`dark:bg-gray-900!` here and at
                        the search box/floating status indicator below carry
                        the same `!important` fix as `<body>`'s own
                        `dark:bg-gray-950!` above, for the same reason: these
                        specific classes were confirmed (by grepping the
                        compiled vendor/tallstackui/tallstackui/dist/tallstackui.css
                        for an unconditional, same-name rule) to collide with
                        a plain utility the vendor bundle also emits.
                        `dark:bg-gray-800`/`dark:hover:bg-gray-700`/
                        `dark:border-gray-800` right next to them were
                        checked the same way and have NO such collision, so
                        they were deliberately left unimportant — re-run the
                        same grep against the vendor file if this app's
                        TallStackUI version ever bumps and dark mode
                        regresses again here.
                    --}}
                    <button type="button"
                            x-on:click="desktop ? $store['tsui.side-bar'].toggle() : (tallStackUiMenuMobile = !tallStackUiMenuMobile)"
                            x-bind:aria-expanded="desktop ? !$store['tsui.side-bar'].collapsed : tallStackUiMenuMobile"
                            aria-label="Toggle navigation"
                            class="grid place-items-center h-8 w-8 rounded-lg bg-gray-100 dark:bg-gray-800! text-gray-500 dark:text-gray-300! hover:bg-gray-200 dark:hover:bg-gray-700! cursor-pointer shrink-0">
                        <x-icon name="bars-3" x-show="!desktop" class="w-4 h-4" />
                        <x-icon name="chevron-double-left" x-show="desktop && !$store['tsui.side-bar'].collapsed" x-cloak class="w-4 h-4" />
                        <x-icon name="chevron-double-right" x-show="desktop && $store['tsui.side-bar'].collapsed" x-cloak class="w-4 h-4" />
                    </button>
                </div>
                {{--
                    Was also a dead, entirely unwired input (no
                    wire:model, no backend). App\Livewire\GlobalSearch —
                    a real cross-document search (Invoices/Quotations/
                    Jobs/Proposals), segmented by section, each row
                    showing the client name plus a number/date/total
                    description. Its own root element already carries
                    the `hidden sm:!block` this div used to provide.
                --}}
                <livewire:global-search :company="$company" :key="'global-search-'.$company->id" />
            </x-slot:left>
            <x-slot:right>
                <div class="flex items-center gap-2">
                    {{--
                        color="blue", not "primary": a general function
                        (create a new record) shouldn't borrow the tenant's
                        own brand color — for Karunia Abadi (brand red)
                        that made "New" read identically to a destructive
                        red action elsewhere on the page. Brand color stays
                        reserved for actual identity chrome (the logo, the
                        active nav item) rather than every button on the
                        page.

                        Was a dead button (no href/wire:click at all) — now
                        a quick-create shortcut to the two document types a
                        user starts from cold (a Job/Quote-linked Invoice
                        still starts from a Quotation, but a standalone
                        Invoice is valid too — see memory.md's Phase 04
                        note). Custom `action` slot, same reason as the
                        avatar/logout dropdown below: keeps this trigger
                        visually identical to the old plain button.
                    --}}
                    <x-dropdown position="bottom-end">
                        <x-slot:action>
                            <div x-on:click="show = !show">
                                <x-button icon="plus" text="New" color="blue" sm class="h-9" />
                            </div>
                        </x-slot:action>
                        <x-dropdown.items text="New invoice" icon="document-currency-dollar" href="{{ route('tallstack.invoices.create', $company) }}" navigate />
                        <x-dropdown.items text="New quotation" icon="document-text" href="{{ route('tallstack.quotations.create', $company) }}" navigate />
                    </x-dropdown>

                    {{--
                        Was also a dead button. Now surfaces the same
                        role-aware Action Queue the Dashboard's own widget
                        shows (App\Support\Dashboard\ActionQueue) — real,
                        already-computed actionable items (overdue
                        invoices, quotations awaiting a decision, etc.),
                        not a new notifications feature/table. Segmented
                        into one labeled section per business area
                        ($actionQueueGroups above), matching this same
                        file's own sidebar $nav groups — the section a
                        notification sits under is the same nav group its
                        own link opens, so clicking it always lands
                        exactly where that section's icon implies.
                    --}}
                    <x-dropdown position="bottom-end" width="sm">
                        <x-slot:action>
                            <div x-on:click="show = !show" class="relative">
                                <x-button icon="bell" color="gray" sm scope="icon-action" class="h-9 w-9" />
                                @if ($actionQueueTotal > 0)
                                    <span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white leading-none">
                                        {{ $actionQueueTotal > 99 ? '99+' : $actionQueueTotal }}
                                    </span>
                                @endif
                            </div>
                        </x-slot:action>
                        <x-slot:header>
                            <div class="px-2 py-1 flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100!">Action queue</span>
                                @if ($actionQueueTotal > 0)
                                    <x-badge text="{{ $actionQueueTotal }}" color="red" sm />
                                @endif
                            </div>
                        </x-slot:header>
                        @forelse ($actionQueueGroups as $group)
                            <div @class(['px-3 pt-2.5 pb-1 flex items-center gap-1.5', 'border-t border-t-gray-100 dark:border-t-gray-800!' => ! $loop->first])>
                                <x-icon name="{{ $group['icon'] }}" class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500!" />
                                <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500!">{{ $group['label'] }}</span>
                            </div>
                            @foreach ($group['items'] as $item)
                                @if ($item['url'])
                                    <x-dropdown.items href="{{ $item['url'] }}" navigate>
                                        <div class="flex items-center justify-between gap-3 w-full">
                                            <span>{{ $item['label'] }}</span>
                                            <x-badge text="{{ $item['count'] }}" :color="match ($item['tone']) { 'danger' => 'red', 'warning' => 'amber', 'info' => 'blue', default => 'gray' }" sm />
                                        </div>
                                    </x-dropdown.items>
                                @else
                                    {{-- Auditor's read-only queue — DESIGN.md §3, matches ActionQueue::readOnly(). --}}
                                    <div class="flex items-center justify-between gap-3 w-full px-3 py-2 text-sm text-gray-500 dark:text-gray-400!">
                                        <span>{{ $item['label'] }}</span>
                                        <x-badge text="{{ $item['count'] }}" color="gray" sm />
                                    </div>
                                @endif
                            @endforeach
                        @empty
                            <div class="px-3 py-4 text-center text-xs text-gray-400">You're all caught up.</div>
                        @endforelse
                    </x-dropdown>
                    {{--
                        The only reachable "Log out" control anywhere in
                        the TALL-stack shell — see App\Http\Controllers\LogoutController's
                        docblock. A plain POST form inside the dropdown
                        item's default slot rather than a Livewire action,
                        so it works the same way regardless of which page
                        component is currently rendering this shell.
                    --}}
                    <x-dropdown position="bottom-end">
                        {{--
                            A custom `action` slot (rather than the
                            component's own `text`/`icon` props) renders
                            with no click-toggle wiring at all — only the
                            auto-generated trigger button gets
                            `x-on:click="show = !show"` — so it has to be
                            added here explicitly, same as the package's
                            own "custom action trigger" doc example.
                        --}}
                        <x-slot:action>
                            <div x-on:click="show = !show" class="cursor-pointer">
                                <x-avatar text="{{ collect(explode(' ', auth()->user()->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}" color="gray" sm class="h-9 w-9" />
                            </div>
                        </x-slot:action>
                        <x-slot:header>
                            <div class="px-2 py-1">
                                <div class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ auth()->user()->name }}</div>
                                <div class="truncate text-xs text-gray-400">{{ auth()->user()->email }}</div>
                            </div>
                        </x-slot:header>
                        <x-dropdown.items text="Passkeys" icon="finger-print" href="{{ route('tallstack.account.passkeys', $company) }}" navigate />
                        <x-dropdown.items separator>
                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 text-left">
                                    <x-icon name="arrow-right-on-rectangle" class="h-4 w-4" />
                                    Log out
                                </button>
                            </form>
                        </x-dropdown.items>
                    </x-dropdown>
                </div>
            </x-slot:right>
        </x-layout.header>
    </x-slot:header>

    {{ $slot }}
</x-layout>

{{--
    A floating status indicator rather than the sidebar's own footer slot:
    the footer sits inside the sidebar's fixed, `overflow-hidden` rail, so
    at the railed (collapsed) width its text had nowhere to go but get
    clipped mid-word. Floating it outside the sidebar avoids that clipping
    entirely and reads like a toast rather than a cut-off label.
--}}
<div x-data="{ open: false, online: true }"
     x-on:mouseenter="open = true"
     x-on:mouseleave="open = false"
     class="fixed bottom-4 left-4 z-40">
    <div x-show="open" x-transition x-cloak
         class="absolute bottom-full left-0 mb-2 w-60 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900! p-3 shadow-xl">
        <div class="flex items-center gap-2">
            <span class="h-2 w-2 rounded-full shrink-0" :class="online ? 'bg-green-500' : 'bg-red-500'"></span>
            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="online ? 'All systems operational' : 'System offline'"></span>
        </div>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400!">KapturInvoice v2.4 &middot; {{ now()->year }}</p>
    </div>
    <div class="flex items-center gap-2 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900! pl-2.5 pr-3 py-1.5 shadow-lg cursor-default">
        <span class="h-2 w-2 rounded-full" :class="online ? 'bg-green-500' : 'bg-red-500'"></span>
        <span class="text-xs font-medium text-gray-600 dark:text-gray-300!" x-text="online ? 'Online' : 'Offline'"></span>
    </div>
</div>

{{-- TallStackUI's own toast notification host — placed once here so every
     TALL-stack page can call `$this->toast()->success(...)->send()`
     (the package's Interactions trait) without re-declaring `<x-toast />`
     itself. --}}
<x-toast />

@livewireScripts
@tallStackUiScript
</body>
</html>
