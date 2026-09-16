@props(['items' => [], 'max' => 2])

{{--
    Shared "secondary row actions" renderer for a table's actions column.
    Each list page keeps its own always-visible primary buttons (Open/eye,
    Download PDF, etc.) inline in its own Blade file — this component only
    covers the conditional/exceptional actions that used to always live
    behind a kebab (Force delete, Approve, Hold, Copy link, ...).

    On a desktop/tablet-width viewport, only the first `max` items (default
    2) render as their own icon buttons, joined into one tight
    <x-button.group> segment rather than loose separate buttons — a page
    with many conditional actions (e.g. Quotations, up to 7 at once) was
    still unreadably busy even side by side. Anything beyond `max` folds
    into a kebab placed right after the group. Below `sm`, EVERY item
    (visible + overflow) collapses into that one kebab instead, so a
    horizontally-scrolling mobile table row never shows more than two
    icons. Pass `$items` as a plain array so each page defines its
    conditional action list exactly once — this component renders it
    across both layouts, so they can never drift apart.

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
@php
    $visibleItems = collect($items)->take($max)->values();
    $overflowItems = collect($items)->slice($max)->values();
@endphp
@if ($visibleItems->isNotEmpty())
    <div class="hidden sm:flex items-center gap-2">
        <x-button.group>
            @foreach ($visibleItems as $item)
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
        </x-button.group>
        @if ($overflowItems->isNotEmpty())
            <x-dropdown icon="ellipsis-vertical" scope="row-action">
                @foreach ($overflowItems as $item)
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
        @endif
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
