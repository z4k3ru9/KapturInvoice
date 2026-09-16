@props(['items' => []])

{{--
    Shared "secondary row actions" renderer for a table's actions column.
    Each list page keeps its own always-visible primary buttons (Open/eye,
    Download PDF, etc.) inline in its own Blade file — this component only
    covers the conditional/exceptional actions that used to always live
    behind a kebab (Force delete, Approve, Hold, Copy link, ...).

    On a desktop/tablet-width viewport there is enough room to show every
    one of those as its own icon button side by side — hiding them behind
    a kebab there just adds an extra click with no space actually saved.
    Below `sm` the same actions collapse into one kebab menu instead, so a
    horizontally-scrolling mobile table row doesn't get crowded with many
    small icon buttons. Pass `$items` as a plain array so each page defines
    its conditional action list exactly once — this component renders it
    twice (buttons + kebab items), so the two can never drift apart.

    Each item: ['text' => ..., 'icon' => ..., 'color' => 'red'|... (default
    gray), 'href' => ..., 'target' => ..., 'click' => 'wire:click value',
    'xclick' => raw Alpine x-on:click value (for a non-Livewire action like
    a clipboard copy — mutually exclusive with 'click'), 'confirm' =>
    'wire:confirm value', 'separator' => bool (kebab only)].
--}}
{{--
    Blade's component-tag compiler scans a tag's whole attribute list via
    regex before any @if/@endif inside it ever compiles — embedding those
    directives between attributes (an earlier version of this file did)
    corrupts that scan and throws a syntax error in every page using this
    component. Every optional attribute below is instead a colon-bound
    expression that evaluates to null when not applicable —
    ComponentAttributeBag::__toString() (vendor/laravel/framework/src/Illuminate/View/ComponentAttributeBag.php)
    already skips null/false attributes when rendering, so this is the
    supported way to make an attribute conditional on a component tag.
--}}
@if (count($items))
    <div class="hidden sm:flex items-center gap-2">
        @foreach ($items as $item)
            <x-button
                icon="{{ $item['icon'] }}"
                :href="$item['href'] ?? null"
                :target="$item['target'] ?? null"
                sm
                :color="$item['color'] ?? 'gray'"
                scope="icon-action"
                class="h-9 w-9"
                :tooltip="$item['text']"
                :wire:click="$item['click'] ?? null"
                :x-on:click="$item['xclick'] ?? null"
                :wire:confirm="$item['confirm'] ?? null"
            />
        @endforeach
    </div>
    <div class="flex sm:hidden">
        <x-dropdown icon="ellipsis-vertical" scope="row-action">
            @foreach ($items as $item)
                <x-dropdown.items
                    :text="$item['text']"
                    icon="{{ $item['icon'] }}"
                    :href="$item['href'] ?? null"
                    :target="$item['target'] ?? null"
                    :wire:click="$item['click'] ?? null"
                    :x-on:click="$item['xclick'] ?? null"
                    :wire:confirm="$item['confirm'] ?? null"
                    :separator="$item['separator'] ?? false"
                />
            @endforeach
        </x-dropdown>
    </div>
@endif
