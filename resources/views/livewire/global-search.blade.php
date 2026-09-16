<div class="relative hidden sm:!block" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
    <div class="relative">
        <x-icon name="magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        {{--
            The header itself is bg-white/dark:bg-gray-900! (see
            AppServiceProvider's registerAppShellDarkModeFix() ->
            layout('header') block) — the exact same tone this input used
            to use, so with the header's own border only appearing at its
            bottom edge, the input had no visible box of its own: no
            background contrast, and a border just one shade off the
            surface it sat on (border-gray-800 on bg-gray-900). It's
            structurally full-width (confirmed via computed style — 869px
            in an 1152px header at a 1440px viewport) but visually read as
            "just an icon and some placeholder text floating in empty
            space" rather than a real search bar, which is what "the
            navbar looks cramped" actually was. A background one step off
            the header's own (bg-gray-50 in light, dark:bg-gray-800! in
            dark — one shade lighter than the header's bg-gray-900) plus a
            more visible border gives it a real, visible boundary at
            whatever width it's actually rendered.
        --}}
        <input type="search"
               wire:model.live.debounce.300ms="query"
               x-on:focus="open = true"
               x-on:input="open = true"
               placeholder="Search invoices, quotations, jobs, proposals…"
               class="h-9 w-full text-sm rounded-lg border-gray-200 bg-gray-50 dark:border-gray-700! dark:bg-gray-800! dark:text-gray-100! dark:placeholder:text-gray-500! pl-9 focus:border-[color:var(--ts-primary)] focus:ring-[color:var(--ts-primary)]">
    </div>

    {{--
        Segmented results — one section per document type, each with a
        richer heading than a plain label: a small colored icon badge
        (the same rounded-square, colored-background treatment every
        <x-stats> card on this app's list pages already uses for its own
        icon, scaled down to fit a dropdown row — see e.g. the
        "Outstanding balance" card on tallstack-invoices.blade.php) so a
        section reads as a distinct, deliberate group rather than a
        plain uppercase label. Each result row underneath shows the
        client's name as the primary line and a "number · date · total"
        description beneath it. Section color is per document type
        (App\Livewire\GlobalSearch's own section builders), drawn from
        this app's existing 5-color semantic palette (gray/red/green/
        amber/blue — AppServiceProvider::registerActionColorPalette()),
        never red (reserved for destructive/danger elsewhere).
    --}}
    <div x-show="open" x-cloak x-transition
         class="absolute z-50 mt-2 w-96 max-h-[28rem] overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-800! bg-white dark:bg-gray-900! shadow-xl">
        @if (trim($query) === '')
            <div class="px-4 py-6 text-center text-sm text-gray-400">Start typing to search invoices, quotations, jobs, and proposals…</div>
        @elseif (mb_strlen(trim($query)) < 2)
            <div class="px-4 py-6 text-center text-sm text-gray-400">Keep typing…</div>
        @elseif (empty($sections))
            <div class="px-4 py-6 text-center text-sm text-gray-400">No results for &quot;{{ $query }}&quot;</div>
        @else
            @foreach ($sections as $section)
                @php
                    $badgeColors = match ($section['color']) {
                        'blue' => 'bg-blue-500 text-blue-50',
                        'amber' => 'bg-amber-500 text-amber-50',
                        'green' => 'bg-green-500 text-green-50',
                        default => 'bg-gray-500 text-gray-50',
                    };
                @endphp
                <div @class(['px-3 pt-3 pb-1.5 flex items-center gap-2', 'border-t border-t-gray-100 dark:border-t-gray-800!' => ! $loop->first])>
                    <span @class(['flex h-5 w-5 shrink-0 items-center justify-center rounded-md', $badgeColors])>
                        <x-icon name="{{ $section['icon'] }}" class="w-3 h-3" />
                    </span>
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400!">{{ $section['label'] }}</span>
                </div>
                @foreach ($section['results'] as $result)
                    <a href="{{ $result['url'] }}" wire:navigate
                       class="block px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-800! transition-colors">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100!">{{ $result['client'] }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400!">{{ $result['description'] }}</div>
                    </a>
                @endforeach
            @endforeach
        @endif
    </div>
</div>
