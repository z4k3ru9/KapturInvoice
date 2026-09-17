@props(['company', 'active'])

{{--
    Phase 12 (F29) settings consolidation, reorganized 2026-09-17 (see
    memory.md) into nine genuinely single-category tabs — Identity,
    Formatting, Branding, Documents & Numbering, Payment Method, Taxes,
    Tax Rates & Lookups, Email & Reminders, Client Portal. Replaces the
    original six-tab set, whose "Company & Taxes" tab had accreted seven
    unrelated concerns (identity, address, document numbering, bank
    accounts, tax config, period lock, a dashboard preference) over time.
    Each tab keeps its own route, its own TallStack{Thing} Livewire
    class, and its own save()/validation logic entirely unchanged — this
    component only adds shared tab-navigation chrome around them.

    Uses TallStackUI's route-based `<x-tab.items href=... navigate>`
    mechanism (see vendor/tallstackui/tallstackui/.ai/components/tab/main.md
    "Route-Based Tabs" and items.blade.php's own `$shouldRender` check,
    TallStackUi\Support\Runtime\Components\TabItemsRuntime::runtime() —
    `! $href || rtrim(request()->url(), '/') === rtrim($href, '/')`) rather
    than merging the Livewire components into one: clicking a tab does
    a real Livewire::navigate() to that setting's own route, so each
    page's own mount()/validate()/save() keeps running exactly as before —
    this file only supplies the tab strip every one of those pages now
    renders itself inside. **Worked around a real vendor-interaction bug**
    (found 2026-09-17, full root-cause writeup in
    docs/out-of-scope-findings.md): `$shouldRender` compares
    `request()->url()` against each tab's own `href`, which is only ever
    true on the page's own initial GET — ANY subsequent Livewire request
    on the page (not just a `.live` field; the plain "Save" button's
    `wire:click="save"` hits this too, confirmed live) has a different
    `request()->url()` (the Livewire update endpoint), so no tab's href
    matches and `items.blade.php` omits `{{ $slot }}` from that response
    entirely — Livewire's morph then strips the already-rendered content
    back out of the DOM, so the whole panel goes blank right after every
    save. Fixed here, not in vendor code: `TabItemsRuntime::runtime()`'s
    own formula is `! $href || (url matches)` — passing `href: null` for
    ONLY the currently-active tab (below) makes `! $href` true
    unconditionally, so that one tab's `shouldRender` is always true
    regardless of `request()->url()`. This doesn't touch the other tabs'
    real hrefs (still needed for click-navigation to them) or break
    initial tab selection (that's driven by this component's own
    `<x-tab selected="{{ $active }}">` prop below, not by any child
    `<x-tab.items>`'s own `x-init` fallback) — the only behavior lost is
    that clicking the tab you're already on won't re-navigate, which
    wasn't meaningful anyway.

    `active` selects which of the nine tab panels below actually receives
    real content ($slot) for THIS request — the current page is the only
    one with anything to render into `$slot` in the first place (this
    component is invoked once, per page, from that page's own view), so
    the other eight panels are intentionally left empty here; they render
    real content only once their own route is the one being requested.

    Tab titles are static strings — a reactive count/badge in a tab's
    `<x-slot:right>` was confirmed this session to cause duplicate tab
    headers on re-render, so none is used here. "Tax Rates & Lookups"
    below uses a literal `&` character, not the `&amp;` entity — writing
    the entity was confirmed this session to double-encode inside
    `<x-tab.items title="...">` (renders literally as `&amp;amp;`).
--}}
@php
    $tabs = [
        'identity' => ['title' => 'Identity', 'route' => route('tallstack.settings.identity', $company)],
        'formatting' => ['title' => 'Formatting', 'route' => route('tallstack.settings.formatting', $company)],
        'branding' => ['title' => 'Branding', 'route' => route('tallstack.settings.branding', $company)],
        'documents-numbering' => ['title' => 'Documents & Numbering', 'route' => route('tallstack.settings.documents-numbering', $company)],
        'payment-method' => ['title' => 'Payment Method', 'route' => route('tallstack.settings.payment-method', $company)],
        'taxes' => ['title' => 'Taxes', 'route' => route('tallstack.settings.taxes', $company)],
        'lookups' => ['title' => 'Tax Rates & Lookups', 'route' => route('tallstack.settings.lookups', $company)],
        'email' => ['title' => 'Email & Reminders', 'route' => route('tallstack.settings.email', $company)],
        'client-portal' => ['title' => 'Client Portal', 'route' => route('tallstack.settings.client-portal', $company)],
    ];
@endphp

<x-tab selected="{{ $active }}" scroll-on-mobile>
    @foreach ($tabs as $key => $tab)
        {{--
            `:title="$tab['title']"` (colon-bound raw PHP, not
            `title="{{ $tab['title'] }}"`) — confirmed live this session
            that the interpolated form double-encodes: Blade's component-tag
            compiler runs `{{ }}`'s own `e()` escape BEFORE handing the
            string to the component prop (turning "Tax Rates & Lookups"
            into "Tax Rates &amp; Lookups" at the PHP-prop level already),
            and this component then passes that prop through Alpine's
            `x-text` (items.blade.php's `x-init="tabs.push({ title:
            @js($title), ... })"`, main.blade.php's
            `<span x-text="item.title ?? item.tab">`) — `x-text` sets
            `textContent`, which does NOT decode HTML entities, so the
            already-escaped "&amp;" renders on screen as the literal text
            "&amp;". The colon-bound form skips Blade's own `{{ }}` escape
            entirely (the prop receives the raw string with a real `&`
            character), so `x-text` displays it correctly as one `&`.
        --}}
        <x-tab.items :tab="$key" :title="$tab['title']" :href="$key === $active ? null : $tab['route']" navigate>
            @if ($key === $active)
                {{ $slot }}
            @endif
        </x-tab.items>
    @endforeach
</x-tab>
