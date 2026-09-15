<x-filament-widgets::widget>
    <x-filament::section heading="Get set up" :description="$progress">
        {{--
            No custom panel theme is wired up (docs/rebuild/outputs/18-stitch-ui-gap-analysis/
            00-scoped-backlog.md A1 "Theme CSS" step is deliberately deferred) — the
            Filament panel only loads Filament's own pre-built stylesheet, not this app's
            resources/css/app.css, so any Tailwind utility class not already part of
            Filament's own compiled CSS silently does nothing. A circular step-indicator
            with connecting lines needs classes Filament doesn't ship — confirmed by
            screenshotting a first attempt, which rendered as plain unstyled text.
            <x-filament::badge> is a real Filament component, so its styling is guaranteed
            to work without that theme.
        --}}
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
            @foreach ($steps as $index => $step)
                <div class="flex items-center gap-2">
                    <x-filament::badge :color="$step['done'] ? 'success' : 'gray'" size="sm">
                        @if ($step['done'])
                            <x-filament::icon icon="heroicon-o-check" class="h-3 w-3" />
                        @else
                            {{ $index + 1 }}
                        @endif
                    </x-filament::badge>

                    @if ($step['done'])
                        <span class="text-sm text-gray-500 line-through dark:text-gray-400">{{ $step['label'] }}</span>
                    @else
                        <x-filament::link :href="$step['url']" class="text-sm">
                            {{ $step['label'] }}
                        </x-filament::link>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
