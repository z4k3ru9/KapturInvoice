@props(['company', 'active' => null, 'title' => null])

@php
    $primary = $company->primary_color ?: '#E63934';
    $logo = $company->getLogoDataUri();
    $adminBase = "/admin/{$company->slug}";

    $nav = [
        'Sales' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => route('tallstack.dashboard', $company), 'icon' => 'squares-2x2'],
            ['key' => 'quotations', 'label' => 'Quotations', 'route' => "{$adminBase}/quotations", 'icon' => 'document-text'],
            ['key' => 'jobs', 'label' => 'Jobs', 'route' => "{$adminBase}/sales-orders", 'icon' => 'briefcase'],
        ],
        'Billing' => [
            ['key' => 'invoices', 'label' => 'Invoices', 'route' => "{$adminBase}/invoices", 'icon' => 'document-currency-dollar'],
            ['key' => 'payments', 'label' => 'Payments', 'route' => "{$adminBase}/payments", 'icon' => 'credit-card'],
            ['key' => 'quotes', 'label' => 'Quotes', 'route' => "{$adminBase}/quotes", 'icon' => 'document-duplicate'],
        ],
        'Procurement' => [
            ['key' => 'vendor-bills', 'label' => 'Vendor bills', 'route' => "{$adminBase}/vendor-bills", 'icon' => 'clipboard-document-list'],
            ['key' => 'vendors', 'label' => 'Vendors', 'route' => "{$adminBase}/vendors", 'icon' => 'building-storefront'],
        ],
        'Clients' => [
            ['key' => 'clients', 'label' => 'Clients', 'route' => "{$adminBase}/clients", 'icon' => 'user-group'],
        ],
        'Catalog' => [
            ['key' => 'products', 'label' => 'Products', 'route' => "{$adminBase}/products", 'icon' => 'cube'],
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
            the sidebar's state — replaced below with a button whose icon
            swaps between collapse/expand so the control's direction is
            visible at a glance. Hide the stock one rather than fork the
            vendor view.

            The replacement button (and the search box beside it) use a
            responsive "hidden md:!grid"/"hidden sm:!block" pattern rather
            than plain "md:grid"/"sm:block": TallStackUI's own compiled
            stylesheet (@tallStackUiStyle, loaded after app.css) redeclares
            a bare `.hidden{display:none}` without redeclaring every
            responsive variant this app uses, so its later-loaded rule
            otherwise wins the cascade over an equal-specificity
            `md:`/`sm:` utility from app.css at any width — the same kind
            of cross-stylesheet Tailwind v4/lightningcss cascade quirk
            already documented and worked around elsewhere in this app.
        */
        header button[aria-label="Toggle sidebar"] { display: none !important; }
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

            @foreach ($nav as $group => $items)
                <div class="px-3 pt-4 pb-1 text-[11px] font-semibold tracking-wide text-gray-400 uppercase">{{ $group }}</div>
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

            <x-slot:footer>
                <div class="px-3 py-3 text-[11px] text-gray-400 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> System online &middot; v2.4
                </div>
            </x-slot:footer>
        </x-side-bar>
    </x-slot:menu>

    <x-slot:header>
        <x-layout.header>
            <x-slot:left>
                <button type="button"
                        x-show="$store['tsui.side-bar'].collapsible"
                        x-on:click="$store['tsui.side-bar'].toggle()"
                        x-bind:aria-expanded="!$store['tsui.side-bar'].collapsed"
                        x-cloak
                        aria-label="Collapse sidebar"
                        class="hidden md:!grid place-items-center w-8 h-8 rounded-lg text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer shrink-0">
                    <x-icon name="chevron-double-left" x-show="!$store['tsui.side-bar'].collapsed" x-cloak class="w-4 h-4" />
                    <x-icon name="chevron-double-right" x-show="$store['tsui.side-bar'].collapsed" x-cloak class="w-4 h-4" />
                </button>
                <div class="hidden sm:!block relative">
                    <x-icon name="magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input type="search" placeholder="Search records, clients, invoices…"
                           class="w-72 text-sm rounded-lg border-gray-200 dark:border-gray-800 dark:bg-gray-900 pl-9 focus:border-[color:var(--ts-primary)] focus:ring-[color:var(--ts-primary)]">
                </div>
            </x-slot:left>
            <x-slot:right>
                <div class="flex items-center gap-2">
                    <x-button icon="plus" text="New" color="primary" sm />
                    <x-button icon="bell" square color="gray" sm />
                    <x-avatar text="{{ collect(explode(' ', auth()->user()->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}" color="gray" sm />
                </div>
            </x-slot:right>
        </x-layout.header>
    </x-slot:header>

    {{ $slot }}
</x-layout>

@livewireScripts
@tallStackUiScript
</body>
</html>
