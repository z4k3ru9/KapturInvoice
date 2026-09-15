@props(['company', 'active' => null, 'title' => null])

@php
    $primary = $company->primary_color ?: '#E63934';
    $logo = $company->getLogoDataUri();
    $adminBase = "/admin/{$company->slug}";

    $nav = [
        'Sales' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => route('tallstack.dashboard', $company), 'icon' => 'squares-2x2'],
            ['key' => 'quotations', 'label' => 'Quotations', 'route' => route('tallstack.quotations', $company), 'icon' => 'document-text'],
            ['key' => 'jobs', 'label' => 'Jobs', 'route' => route('tallstack.jobs', $company), 'icon' => 'briefcase'],
        ],
        'Billing' => [
            ['key' => 'invoices', 'label' => 'Invoices', 'route' => route('tallstack.invoices', $company), 'icon' => 'document-currency-dollar'],
            ['key' => 'payments', 'label' => 'Payments', 'route' => route('tallstack.payments', $company), 'icon' => 'credit-card'],
            ['key' => 'quotes', 'label' => 'Quotes', 'route' => "{$adminBase}/quotes", 'icon' => 'document-duplicate'],
        ],
        // Mirrors App\Filament\Resources\Proposals\ProposalResource's own
        // navigationGroup ('Proposals', a group of its own rather than
        // folded into Sales/Billing) — that resource's own
        // shouldRegisterNavigation() is false so it never actually shows
        // in the Filament sidebar, but this is still the grouping the
        // resource itself declares.
        'Proposals' => [
            ['key' => 'proposals', 'label' => 'Proposals', 'route' => route('tallstack.proposals', $company), 'icon' => 'presentation-chart-bar'],
        ],
        'Procurement' => [
            ['key' => 'vendor-bills', 'label' => 'Vendor bills', 'route' => route('tallstack.vendor-bills', $company), 'icon' => 'clipboard-document-list'],
            ['key' => 'vendor-purchase-orders', 'label' => 'Purchase orders', 'route' => route('tallstack.vendor-purchase-orders', $company), 'icon' => 'shopping-cart'],
            ['key' => 'vendors', 'label' => 'Vendors', 'route' => route('tallstack.vendors', $company), 'icon' => 'building-storefront'],
        ],
        // Phase 7 (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md)
        // — matches AdminPanelProvider's own pinned nav-group order (Sales
        // → Procurement → Delivery → Catalog → Reports → Settings) and the
        // Stitch "Delivery Orders & Handover Register" mockup's own
        // sidebar, which places this group in the same spot.
        'Delivery' => [
            ['key' => 'delivery-orders', 'label' => 'Delivery Orders', 'route' => route('tallstack.delivery-orders', $company), 'icon' => 'truck'],
            ['key' => 'handover-reports', 'label' => 'Handover Reports', 'route' => route('tallstack.handover-reports', $company), 'icon' => 'document-check'],
        ],
        'Clients' => [
            ['key' => 'clients', 'label' => 'Clients', 'route' => route('tallstack.clients', $company), 'icon' => 'user-group'],
        ],
        'Catalog' => [
            ['key' => 'products', 'label' => 'Products', 'route' => route('tallstack.products', $company), 'icon' => 'cube'],
            // Deferred item, built alongside Products/Catalog
            // (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md) —
            // folded into this SAME 'Catalog' key, never a second
            // 'Catalog' => [...] block (see this file's own duplicate-key
            // warning further down).
            ['key' => 'price-list-items', 'label' => 'Price List', 'route' => route('tallstack.price-list-items', $company), 'icon' => 'currency-dollar'],
        ],
        // Phase 9 (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md)
        // — matches AdminPanelProvider's own pinned nav-group order
        // (... Reports → Settings, last) and the Stitch "Company & Taxes
        // Settings"/"Settings — Tax Rates & Small Lookups" mockups' own
        // sidebar, which both place this group last too.
        // "Users & Roles" is administrative/settings-adjacent (matches
        // Filament's own UserResource, App\Filament\Resources\Users\
        // UserResource, navigationGroup 'Team' — kept under this app's own
        // "Settings" grouping here since a standalone one-item nav group
        // reads as noise at this sidebar width) — folded into the same
        // 'Settings' key as the Phase 9 pages below it. PHP array literals
        // silently let a later duplicate key overwrite an earlier one, so
        // these MUST stay merged into one array, never split into two
        // 'Settings' => [...] entries again.
        'Settings' => [
            ['key' => 'settings-company-taxes', 'label' => 'Company & Taxes', 'route' => route('tallstack.settings.company-and-taxes', $company), 'icon' => 'adjustments-horizontal'],
            ['key' => 'settings-email', 'label' => 'Email & Reminders', 'route' => route('tallstack.settings.email', $company), 'icon' => 'envelope'],
            ['key' => 'settings-branding', 'label' => 'Branding', 'route' => route('tallstack.settings.branding', $company), 'icon' => 'swatch'],
            ['key' => 'settings-lookups', 'label' => 'Tax Rates & Lookups', 'route' => route('tallstack.settings.lookups', $company), 'icon' => 'receipt-percent'],
            ['key' => 'users', 'label' => 'Users & Roles', 'route' => route('tallstack.users', $company), 'icon' => 'user-group'],
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
<body class="antialiased bg-gray-50 dark:bg-gray-950">

<x-layout>
    <x-slot:menu>
        <x-side-bar collapsible thin-scroll>
            <x-slot:brand>
                <div class="flex items-center gap-3 px-1">
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
                 room in the railed width and only ever showed truncated. --}}
            <x-slot:brandCollapsed>
                <div class="flex items-center justify-center px-1">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="{{ $company->name }}" class="w-8 h-8 rounded-lg object-cover shrink-0">
                    @else
                        <span class="w-8 h-8 rounded-lg grid place-items-center text-white font-bold text-sm shrink-0"
                              style="background: {{ $primary }}">{{ mb_substr($company->name, 0, 1) }}</span>
                    @endif
                </div>
            </x-slot:brandCollapsed>

            @foreach ($nav as $group => $items)
                {{--
                    A plain text label truncates/wraps badly at the railed
                    (collapsed) width — x-side-bar.separator is TallStackUI's
                    own collapse-aware group divider: it shows the label
                    when expanded and fades to just the rule line when
                    railed, so nothing gets forced to show past its width.
                --}}
                <x-side-bar.separator text="{{ $group }}" line scope="nav" />
                @foreach ($items as $item)
                    {{--
                        The component's own default text color is
                        `text-primary-500` on every item, current or not —
                        `!` (important) utilities override it so only the
                        current route reads in the tenant's brand color,
                        matching the Stitch mockup's muted/active contrast.
                    --}}
                    <x-side-bar.item
                        :text="$item['label']"
                        :href="$item['route']"
                        :current="$active === $item['key']"
                        :class="$active === $item['key'] ? '' : '!text-gray-600 dark:!text-gray-300'"
                    >
                        <x-slot:icon>
                            <x-icon :name="$item['icon']" class="w-[18px] h-[18px]" />
                        </x-slot:icon>
                    </x-side-bar.item>
                @endforeach
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
                    --}}
                    <button type="button"
                            x-on:click="desktop ? $store['tsui.side-bar'].toggle() : (tallStackUiMenuMobile = !tallStackUiMenuMobile)"
                            x-bind:aria-expanded="desktop ? !$store['tsui.side-bar'].collapsed : tallStackUiMenuMobile"
                            aria-label="Toggle navigation"
                            class="grid place-items-center h-8 w-8 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 cursor-pointer shrink-0">
                        <x-icon name="bars-3" x-show="!desktop" class="w-4 h-4" />
                        <x-icon name="chevron-double-left" x-show="desktop && !$store['tsui.side-bar'].collapsed" x-cloak class="w-4 h-4" />
                        <x-icon name="chevron-double-right" x-show="desktop && $store['tsui.side-bar'].collapsed" x-cloak class="w-4 h-4" />
                    </button>
                </div>
                <div class="hidden sm:!block relative">
                    <x-icon name="magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input type="search" placeholder="Search records, clients, invoices…"
                           class="h-9 w-72 text-sm rounded-lg border-gray-200 dark:border-gray-800 dark:bg-gray-900 pl-9 focus:border-[color:var(--ts-primary)] focus:ring-[color:var(--ts-primary)]">
                </div>
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
                    --}}
                    <x-button icon="plus" text="New" color="blue" sm class="h-9" />
                    <x-button icon="bell" color="gray" sm scope="icon-action" class="h-9 w-9" />
                    <x-avatar text="{{ collect(explode(' ', auth()->user()->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}" color="gray" sm class="h-9 w-9" />
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
         class="absolute bottom-full left-0 mb-2 w-60 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-3 shadow-xl">
        <div class="flex items-center gap-2">
            <span class="h-2 w-2 rounded-full shrink-0" :class="online ? 'bg-green-500' : 'bg-red-500'"></span>
            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="online ? 'All systems operational' : 'System offline'"></span>
        </div>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">KapturInvoice v2.4 &middot; {{ now()->year }}</p>
    </div>
    <div class="flex items-center gap-2 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 pl-2.5 pr-3 py-1.5 shadow-lg cursor-default">
        <span class="h-2 w-2 rounded-full" :class="online ? 'bg-green-500' : 'bg-red-500'"></span>
        <span class="text-xs font-medium text-gray-600 dark:text-gray-300" x-text="online ? 'Online' : 'Offline'"></span>
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
