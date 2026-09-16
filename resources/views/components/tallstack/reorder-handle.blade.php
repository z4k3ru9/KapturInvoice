{{--
    Simplified per an explicit request ("too many with up down arrows") —
    used to also render keyboard-accessible up/down buttons alongside the
    drag handle. Now just the drag handle itself; dragging the row (via
    the <tr>'s own draggable/dragstart/dragend/dragover/drop wiring in
    x-tallstack.reorderable-item-row) is the only way to reorder. Placed
    at the rightmost end of the row's actions, after Edit/Delete, not
    before them.
--}}
<span class="cursor-move text-gray-400 shrink-0 px-1" title="Drag to reorder" aria-hidden="true">
    <x-icon name="bars-3" class="w-4 h-4" />
</span>
