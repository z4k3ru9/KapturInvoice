<div class="relative hidden sm:!block" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
    <div class="relative">
        <x-icon name="magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input type="search"
               wire:model.live.debounce.300ms="query"
               x-on:focus="open = true"
               x-on:input="open = true"
               placeholder="Search invoices, quotations, jobs, proposals…"
               class="h-9 w-72 text-sm rounded-lg border-gray-200 dark:border-gray-800! dark:bg-gray-900! dark:text-gray-100! dark:placeholder:text-gray-500! pl-9 focus:border-[color:var(--ts-primary)] focus:ring-[color:var(--ts-primary)]">
    </div>

    {{--
        Segmented results — one labeled section per document type (the
        same icon+label section-header treatment as the shell's own
        notification bell, resources/views/components/tallstack/app.blade.php),
        each row showing the client's name as the primary line and a
        "number · date · total" description underneath.
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
                <div @class(['px-3 pt-2.5 pb-1 flex items-center gap-1.5', 'border-t border-t-gray-100 dark:border-t-gray-800!' => ! $loop->first])>
                    <x-icon name="{{ $section['icon'] }}" class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500!" />
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500!">{{ $section['label'] }}</span>
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
