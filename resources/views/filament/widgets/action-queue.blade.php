<x-filament-widgets::widget>
    <x-filament::section heading="Action queue">
        @forelse ($items as $item)
            <div class="flex items-center justify-between gap-4 border-b border-gray-100 py-2 last:border-b-0 dark:border-white/5">
                <div class="flex items-center gap-3">
                    <x-filament::badge :color="$item['tone']">
                        {{ $item['count'] }}
                    </x-filament::badge>

                    <span class="text-sm font-medium text-gray-950 dark:text-white">
                        {{ $item['label'] }}
                    </span>
                </div>

                @if ($item['url'])
                    <x-filament::link :href="$item['url']">
                        View
                    </x-filament::link>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">Nothing needs your attention.</p>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
