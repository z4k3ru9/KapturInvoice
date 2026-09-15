<x-filament-widgets::widget>
    <x-filament::section heading="Get set up" :description="$progress">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            @foreach ($steps as $step)
                <div class="flex items-start gap-2">
                    <x-filament::icon
                        :icon="$step['done'] ? 'heroicon-o-check-circle' : 'heroicon-o-minus-circle'"
                        :class="$step['done'] ? 'h-5 w-5 text-success-500' : 'h-5 w-5 text-gray-400'"
                    />

                    <div>
                        @if ($step['done'])
                            <span class="text-sm text-gray-500 line-through dark:text-gray-400">{{ $step['label'] }}</span>
                        @else
                            <x-filament::link :href="$step['url']" class="text-sm">
                                {{ $step['label'] }}
                            </x-filament::link>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
