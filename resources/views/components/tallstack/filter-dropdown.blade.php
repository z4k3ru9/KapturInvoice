@props(['label', 'options', 'active', 'method'])

{{--
    Replaces the app-wide "row of pill buttons" status/type/brand filter
    pattern (11 list pages had it hand-copied verbatim — Invoices, Payments,
    Quotations, Jobs, Vendor Bills, Vendor Purchase Orders, Proposals,
    Client Portal Invitations, Documents, Products, Price List Items) with
    a single dropdown, per an explicit simplification request: the pill row
    wrapped into 2-4 stacked rows at mobile width on every one of those
    pages (confirmed live, e.g. Jobs' 9 pills wrapped to 4 rows at 375px),
    pushing real content below the fold before a user saw any data.

    Reuses the existing "toolbar" x-dropdown scope (already used by the
    Dashboard/Reports period selector) rather than a form <x-select.styled>
    — the underlying filter is still a plain wire:click method call on the
    owning Livewire component (e.g. filterStatus($value)), not a two-way
    wire:model binding, so every page's existing method (which in several
    cases also calls resetPage()) keeps working completely unchanged; only
    the visual affordance changes from a pill row to a dropdown trigger.

    $label: current selection's display text, shown on the dropdown trigger
        (e.g. "All statuses", or the active status's own label).
    $options: ordered LIST (not value => label pairs — PHP silently casts a
        null array key to '', which would corrupt an "All" entry's real
        null value) of ['value' => mixed, 'label' => string] — value may be
        null for an "All" entry, a string/int enum value, or an int id.
    $active: the currently selected value, compared with === against each
        option's own value to render its check/highlight state.
    $method: the Livewire method name to call on selection, invoked as
        {method}(value) via wire:click — value is passed through Blade's
        own @js() so null/string/int all serialize correctly.
--}}
<x-dropdown text="{{ $label }}" icon="funnel" scope="toolbar">
    @foreach ($options as $option)
        <x-dropdown.items
            text="{{ $option['label'] }}"
            :icon="$active === $option['value'] ? 'check' : null"
            wire:click="{{ $method }}(@js($option['value']))"
        />
    @endforeach
</x-dropdown>
