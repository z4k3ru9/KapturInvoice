@props(['reorderable' => false, 'reorderMethod' => 'reorderItems'])

{{--
    Phase 06B Slice 3 dynamic-row reorder, rebuilt as a plain Alpine
    component after the Filament removal (this app's "plain Alpine, no
    extra JS framework" convention — see resources/js/app.js). Native
    HTML5 Drag and Drop (draggable/dragstart/dragover/drop), never a
    third-party sortable library, since TallStackUI's own <x-table>
    doesn't expose row-level drag hooks (checked
    vendor/tallstackui/tallstackui/src/resources/views/components/table)
    and this app already avoids adding JS dependencies beyond what
    TallStackUI itself ships. Every reorder — drag-drop or a keyboard
    move button (x-tallstack.reorderable-item-row) — recomputes the full
    ordered id list from the CURRENT DOM row order at the moment of the
    action (never a separately cached array), so it can never go stale
    after a Livewire re-render adds/removes a row (e.g. the add/edit item
    modal). One `$wire.{{ $reorderMethod }}($ids)` call per action —
    never one write per intermediate drag position.

    `reorderable` gates the drag handle/keyboard buttons in the UI only
    — the paired Livewire method is the real authority and re-checks the
    owning document's own Draft-equivalent status server-side; this flag
    only avoids wiring dead affordances into the DOM once a document has
    left Draft.
--}}
<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800"
     @if ($reorderable)
        x-data="{
            dragId: null,
            dragStart(id) { this.dragId = id; },
            dragEnd() { this.dragId = null; },
            drop(targetId, el) {
                const draggedId = this.dragId;
                this.dragId = null;

                if (draggedId === null || draggedId === targetId) {
                    return;
                }

                const rows = Array.from(el.closest('tbody').querySelectorAll('tr[data-item-row]'));
                const ids = rows.map((row) => parseInt(row.dataset.itemRow, 10));
                const from = ids.indexOf(draggedId);
                const to = ids.indexOf(targetId);

                if (from === -1 || to === -1) {
                    return;
                }

                ids.splice(to, 0, ids.splice(from, 1)[0]);
                $wire.{{ $reorderMethod }}(ids);
            },
            move(id, direction, el) {
                const rows = Array.from(el.closest('tbody').querySelectorAll('tr[data-item-row]'));
                const ids = rows.map((row) => parseInt(row.dataset.itemRow, 10));
                const from = ids.indexOf(id);
                const to = from + direction;

                if (from === -1 || to < 0 || to >= ids.length) {
                    return;
                }

                [ids[from], ids[to]] = [ids[to], ids[from]];
                $wire.{{ $reorderMethod }}(ids);
            },
        }"
     @endif
>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 border-b border-gray-200 dark:border-gray-800">
                <th class="w-14 px-2 py-2"><span class="sr-only">Reorder</span></th>
                {{ $head }}
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
