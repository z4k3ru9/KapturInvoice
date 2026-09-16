@props(['id', 'first' => false, 'last' => false])

{{--
    Extracted out of x-tallstack.reorderable-item-row (which used to render
    this as its own always-leading <td>, on the opposite side of the row
    from the Edit/Delete buttons) so a page can place it directly next to
    Edit/Delete instead — "tidy up all the actions on one side," per an
    explicit request. The row's own drag/drop wiring (draggable, dragstart/
    dragend/dragover/drop) stays entirely on the <tr> in
    reorderable-item-row.blade.php; this is only the visible handle +
    keyboard-accessible up/down buttons that call into that same
    table's move()/drop() Alpine methods.
--}}
<div class="flex items-center gap-1 text-gray-400 shrink-0">
    <span class="cursor-move" title="Drag to reorder" aria-hidden="true">
        <x-icon name="bars-3" class="w-3.5 h-3.5" />
    </span>
    <div class="flex flex-col">
        <button type="button"
                @unless ($first) x-on:click="move({{ $id }}, -1, $el)" @endunless
                @disabled($first)
                class="p-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-30 disabled:cursor-not-allowed"
                aria-label="Move item up">
            <x-icon name="chevron-up" class="w-3.5 h-3.5" />
        </button>
        <button type="button"
                @unless ($last) x-on:click="move({{ $id }}, 1, $el)" @endunless
                @disabled($last)
                class="p-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-30 disabled:cursor-not-allowed"
                aria-label="Move item down">
            <x-icon name="chevron-down" class="w-3.5 h-3.5" />
        </button>
    </div>
</div>
