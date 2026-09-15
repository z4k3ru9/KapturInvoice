{{--
    Searchable "pick an existing category or type a new one" picker for a
    plain nullable string `category` column — not a new lookup table. Wraps
    TallStackUI's <x-select.styled>, which supports this affordance
    natively via its `after` slot (see
    vendor/tallstackui/tallstackui/.ai/components/form/select/styled.md,
    "After Slot (Custom Action When Empty)"): that slot renders whenever
    the current search term has zero matches, so a button inside it that
    calls the component's own Alpine `select()` with the typed term commits
    it as the value — no separate "Add new" mode/state needed.

    Because `select()` is called with a plain string (not a `{label,
    value}` pair), the component runs in non-dimensional mode and sets
    wire:model directly to that string, exactly like picking any other
    option.

    Usage:
        <x-tallstack.category-select wire:model="category" :options="$categories" />

    `$options` must already be a plain list of distinct category strings
    scoped to the current tenant — the caller resolves that (e.g.
    `PriceListItem::query()->where('company_id', $company->id)->distinct()->pluck('category')->filter()`),
    this component only renders the picker.

    Established by the Price List Items category field (repair plan Phase
    10a). Phase 10b (Products) reuses this same component verbatim against
    its own distinct-category query per decision gate G3 (share one
    taxonomy with Price List Items).
--}}
@props([
    'options' => [],
    'label' => 'Category',
    'placeholder' => 'Select or type a new category…',
])

<x-select.styled
    {{ $attributes }}
    :label="$label"
    :options="collect($options)->filter()->unique()->sort()->values()->all()"
    searchable
    :placeholders="[
        'default' => $placeholder,
        'search' => 'Search or type a new category…',
        'empty' => 'No matching category.',
    ]"
>
    <x-slot:after>
        <div class="px-2 pb-2">
            <button
                type="button"
                x-show="search.trim().length > 0"
                x-on:click="select(search.trim()); show = false"
                class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-xs font-medium text-[color:var(--ts-primary)] hover:bg-gray-50 dark:hover:bg-gray-800"
            >
                <x-icon name="plus-circle" class="w-4 h-4 shrink-0" />
                <span>Add "<span x-text="search.trim()"></span>" as a new category</span>
            </button>
            <p class="px-3 py-2 text-xs text-gray-400" x-show="search.trim().length === 0">
                Type to search existing categories or add a new one.
            </p>
        </div>
    </x-slot:after>
</x-select.styled>
