@props(['company', 'active'])

{{--
    Phase 12 (F29) settings consolidation — a shared tab-header wrapper for
    the six formerly-separate settings pages (Company & Taxes, Branding,
    Tax Rates & Lookups, Numbering, Email & Reminders, Client Portal), each
    of which keeps its own route, its own TallStack{Thing} Livewire class,
    and its own save()/validation logic entirely unchanged — this
    component only adds shared tab-navigation chrome around them.

    Uses TallStackUI's route-based `<x-tab.items href=... navigate>`
    mechanism (see vendor/tallstackui/tallstackui/.ai/components/tab/main.md
    "Route-Based Tabs" and items.blade.php's own `$shouldRender` check,
    TallStackUi\Support\Runtime\Components\TabItemsRuntime::runtime() —
    `! $href || rtrim(request()->url(), '/') === rtrim($href, '/')`) rather
    than merging the six Livewire components into one: clicking a tab does
    a real Livewire::navigate() to that setting's own route, so each
    page's own mount()/validate()/save() keeps running exactly as before —
    this file only supplies the six-item tab strip every one of those
    pages now renders itself inside.

    `active` selects which of the six tab panels below actually receives
    real content ($slot) for THIS request — the current page is the only
    one with anything to render into `$slot` in the first place (this
    component is invoked once, per page, from that page's own view), so
    the other five panels are intentionally left empty here; they render
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
        'company-and-taxes' => ['title' => 'Company & Taxes', 'route' => route('tallstack.settings.company-and-taxes', $company)],
        'branding' => ['title' => 'Branding', 'route' => route('tallstack.settings.branding', $company)],
        'lookups' => ['title' => 'Tax Rates & Lookups', 'route' => route('tallstack.settings.lookups', $company)],
        'numbering' => ['title' => 'Numbering', 'route' => route('tallstack.settings.numbering', $company)],
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
        <x-tab.items :tab="$key" :title="$tab['title']" :href="$tab['route']" navigate>
            @if ($key === $active)
                {{ $slot }}
            @endif
        </x-tab.items>
    @endforeach
</x-tab>
