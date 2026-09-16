@props(['primary' => [], 'items' => [], 'max' => 2])

{{--
    Shared row-actions renderer for a table's actions column. Renders the
    page's own always-visible primary actions (Open/eye, Download PDF,
    etc. — pass as `primary`) together with its conditional/exceptional
    actions (Force delete, Approve, Hold, Copy link, ... — pass as
    `items`) as ONE seamless <x-button.group> segment — no gap, no
    separately-floating icons — matching this app's button-group visual
    convention everywhere else. `items` beyond `max` (default 2) fold into
    a kebab that sits flush as the group's own trailing segment (via the
    `row-action-joined` dropdown scope, AppServiceProvider.php) rather
    than a separate gapped icon. Below `sm`, EVERY conditional item (not
    just the overflow) collapses into that same trailing kebab, since a
    horizontally-scrolling mobile row has no room for more than the
    primary actions plus one menu trigger.

    Each item (both `primary` and `items`): ['text' => ..., 'icon' => ...,
    'color' => 'red'|... (default gray), 'href' => ..., 'target' => ...,
    'click' => 'wire:click value', 'xclick' => raw Alpine x-on:click value
    (for a non-Livewire action like a clipboard copy — mutually exclusive
    with 'click'), 'confirm' => 'wire:confirm value', 'separator' => bool
    (kebab only, ignored for `primary`/visible-group items).
--}}
{{--
    Blade's component-tag compiler scans a tag's whole attribute list via
    regex before any @if/@endif inside it ever compiles — embedding those
    directives between attributes corrupts that scan and throws a syntax
    error. Every optional attribute below is instead a colon-bound
    expression that evaluates to null when not applicable —
    ComponentAttributeBag::__toString() (vendor/laravel/framework/src/Illuminate/View/ComponentAttributeBag.php)
    already skips null/false attributes when rendering, so this is the
    supported way to make an attribute conditional on a component tag.
--}}
@php
    $primaryItems = collect($primary)->values();
    $visibleExtras = collect($items)->take($max)->values();
    $overflowItems = collect($items)->slice($max)->values();

    $desktopVisible = $primaryItems->concat($visibleExtras);
    $mobileVisible = $primaryItems;
    $mobileKebabItems = collect($items)->values();

    // A kebab-menu row has no colored button background to signal
    // "destructive"/"positive" the way the visible icon buttons do
    // (x-button's own color prop) — every item defaults to the same
    // neutral gray text/icon (vendor/tallstackui/tallstackui/src/Components/Dropdown/Items/Component.php's
    // own 'icon.base' => 'text-gray-500'). Force the icon to match the
    // item's own semantic color instead, via a descendant-svg selector
    // with `!` (this app's established cross-stylesheet-collision fix,
    // .ai/rules/tallstackui-customization.md) since the icon's hardcoded
    // class otherwise wins on source order alone.
    $kebabIconColorClass = function (array $item): ?string {
        $color = $item['color'] ?? null;

        return match ($color) {
            'red' => '[&>svg]:text-red-600! dark:[&>svg]:text-red-400!',
            'green' => '[&>svg]:text-green-600! dark:[&>svg]:text-green-400!',
            'amber' => '[&>svg]:text-amber-600! dark:[&>svg]:text-amber-400!',
            'blue' => '[&>svg]:text-blue-600! dark:[&>svg]:text-blue-400!',
            default => null,
        };
    };
@endphp
@if ($desktopVisible->isNotEmpty() || $mobileVisible->isNotEmpty())
    {{-- Desktop/tablet --}}
    <div class="hidden sm:flex items-center">
        @if ($desktopVisible->isNotEmpty())
            @if ($overflowItems->isEmpty())
                <x-button.group>
                    @foreach ($desktopVisible as $item)
                        <x-button
                            icon="{{ $item['icon'] }}"
                            :href="$item['href'] ?? null"
                            :target="$item['target'] ?? null"
                            sm
                            :color="$item['color'] ?? 'gray'"
                            scope="icon-action"
                            class="h-9 w-9"
                            :tooltip="$item['text']"
                            :aria-label="$item['text']"
                            :wire:click="$item['click'] ?? null"
                            :x-on:click="$item['xclick'] ?? null"
                            :wire:confirm="$item['confirm'] ?? null"
                        />
                    @endforeach
                </x-button.group>
            @else
                {{-- A kebab follows, so this group's own last child must
                     NOT get the vendor group's usual rounded-r-md — the
                     kebab (row-action-joined scope) owns the right end
                     instead. Same base/overlap classes as
                     vendor/tallstackui/tallstackui/src/Components/Button/Group/Component.php's
                     own 'wrapper.base'/'wrapper.layout.horizontal', minus
                     the last-child right-rounding. --}}
                <div class="isolate inline-flex shadow-xs [&>*]:relative [&>*:focus]:z-10 [&>*]:rounded-none [&>*:first-child]:rounded-l-md [&>*:not(:first-child)]:-ml-px">
                    @foreach ($desktopVisible as $item)
                        <x-button
                            icon="{{ $item['icon'] }}"
                            :href="$item['href'] ?? null"
                            :target="$item['target'] ?? null"
                            sm
                            :color="$item['color'] ?? 'gray'"
                            scope="icon-action"
                            class="h-9 w-9"
                            :tooltip="$item['text']"
                            :aria-label="$item['text']"
                            :wire:click="$item['click'] ?? null"
                            :x-on:click="$item['xclick'] ?? null"
                            :wire:confirm="$item['confirm'] ?? null"
                        />
                    @endforeach
                </div>
                <x-dropdown icon="ellipsis-vertical" scope="row-action-joined">
                    @foreach ($overflowItems as $item)
                        <x-dropdown.items
                            :text="$item['text']"
                            icon="{{ $item['icon'] }}"
                            :href="$item['href'] ?? null"
                            :target="$item['target'] ?? null"
                            class="{{ $kebabIconColorClass($item) }}"
                            :wire:click="$item['click'] ?? null"
                            :x-on:click="$item['xclick'] ?? null"
                            :wire:confirm="$item['confirm'] ?? null"
                            :separator="$item['separator'] ?? false"
                        />
                    @endforeach
                </x-dropdown>
            @endif
        @endif
    </div>
    {{-- Mobile --}}
    <div class="flex sm:hidden items-center">
        @if ($mobileVisible->isNotEmpty())
            @if ($mobileKebabItems->isEmpty())
                <x-button.group>
                    @foreach ($mobileVisible as $item)
                        <x-button
                            icon="{{ $item['icon'] }}"
                            :href="$item['href'] ?? null"
                            :target="$item['target'] ?? null"
                            sm
                            :color="$item['color'] ?? 'gray'"
                            scope="icon-action"
                            class="h-9 w-9"
                            :tooltip="$item['text']"
                            :aria-label="$item['text']"
                            :wire:click="$item['click'] ?? null"
                            :x-on:click="$item['xclick'] ?? null"
                            :wire:confirm="$item['confirm'] ?? null"
                        />
                    @endforeach
                </x-button.group>
            @else
                <div class="isolate inline-flex shadow-xs [&>*]:relative [&>*:focus]:z-10 [&>*]:rounded-none [&>*:first-child]:rounded-l-md [&>*:not(:first-child)]:-ml-px">
                    @foreach ($mobileVisible as $item)
                        <x-button
                            icon="{{ $item['icon'] }}"
                            :href="$item['href'] ?? null"
                            :target="$item['target'] ?? null"
                            sm
                            :color="$item['color'] ?? 'gray'"
                            scope="icon-action"
                            class="h-9 w-9"
                            :tooltip="$item['text']"
                            :aria-label="$item['text']"
                            :wire:click="$item['click'] ?? null"
                            :x-on:click="$item['xclick'] ?? null"
                            :wire:confirm="$item['confirm'] ?? null"
                        />
                    @endforeach
                </div>
                <x-dropdown icon="ellipsis-vertical" scope="row-action-joined">
                    @foreach ($mobileKebabItems as $item)
                        <x-dropdown.items
                            :text="$item['text']"
                            icon="{{ $item['icon'] }}"
                            :href="$item['href'] ?? null"
                            :target="$item['target'] ?? null"
                            class="{{ $kebabIconColorClass($item) }}"
                            :wire:click="$item['click'] ?? null"
                            :x-on:click="$item['xclick'] ?? null"
                            :wire:confirm="$item['confirm'] ?? null"
                            :separator="$item['separator'] ?? false"
                        />
                    @endforeach
                </x-dropdown>
            @endif
        @elseif ($mobileKebabItems->isNotEmpty())
            <x-dropdown icon="ellipsis-vertical" scope="row-action">
                @foreach ($mobileKebabItems as $item)
                    <x-dropdown.items
                        :text="$item['text']"
                        icon="{{ $item['icon'] }}"
                        :href="$item['href'] ?? null"
                        :target="$item['target'] ?? null"
                        class="{{ $kebabIconColorClass($item) }}"
                        :wire:click="$item['click'] ?? null"
                        :x-on:click="$item['xclick'] ?? null"
                        :wire:confirm="$item['confirm'] ?? null"
                        :separator="$item['separator'] ?? false"
                    />
                @endforeach
            </x-dropdown>
        @endif
    </div>
@endif
