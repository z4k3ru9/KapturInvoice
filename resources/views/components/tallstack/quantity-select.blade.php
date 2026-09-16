@props(['options' => [10, 25, 50, 100]])

{{--
    A compact page-size selector, bound to the page's own public
    `$quantity` property, meant to sit inline in the same header row as
    the status filter-dropdown and search input (see any TallStack{Thing}
    list page's `<x-slot:header>`).

    Deliberately NOT `<x-table :filter="['quantity' => 'quantity']">` —
    the vendor component always renders that in its own dedicated row
    ABOVE the table (vendor/tallstackui/tallstackui/src/resources/views/components/table/main.blade.php,
    the `filter.wrapper` block), which cannot be repositioned via Soft
    Customization (it's a layout position, not a class list) and wasted
    a full row of vertical space on every list page for one small
    control. This plain `<select>` reproduces the same
    `$quantity`-driven pagination with no fixed position of its own.
--}}
<select wire:model.live="quantity"
        class="h-9 rounded-lg border-gray-200 dark:border-gray-800! dark:bg-gray-900! dark:text-gray-100! text-sm pl-2.5 pr-7 focus:border-[color:var(--ts-primary)] focus:ring-[color:var(--ts-primary)] shrink-0"
        aria-label="Rows per page">
    @foreach ($options as $option)
        <option value="{{ $option }}">{{ $option }} / page</option>
    @endforeach
</select>
