@props(['id', 'reorderable' => false, 'first' => false, 'last' => false])

{{--
    One line-item row for x-tallstack.reorderable-items-table. `data-item-row`
    is the DOM anchor the table's own drag/keyboard-move Alpine handlers
    read the live row order from (see that component's docblock) — every
    row MUST carry it, reorderable document or not, so a delete/re-add
    doesn't leave a stale id in the computed order.

    The reorder handle itself no longer renders here as an automatic
    leading column — it moved to x-tallstack.reorder-handle, which the
    page places directly next to its own Edit/Delete buttons instead
    ("tidy up all the actions on one side," per an explicit request). This
    component still owns every drag/drop attribute on the <tr> itself
    (unchanged) since that's independent of where the visible handle sits.
--}}
<tr
    data-item-row="{{ $id }}"
    wire:key="item-row-{{ $id }}"
    class="border-b border-gray-100 dark:border-gray-800! last:border-b-0"
    @if ($reorderable)
        draggable="true"
        x-on:dragstart="dragStart({{ $id }})"
        x-on:dragend="dragEnd()"
        x-on:dragover.prevent
        x-on:drop.prevent="drop({{ $id }}, $el)"
        x-bind:class="dragId === {{ $id }} ? 'opacity-40' : ''"
    @endif
>
    {{ $slot }}
</tr>
