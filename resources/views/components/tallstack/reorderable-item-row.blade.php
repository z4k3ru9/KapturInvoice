@props(['id', 'reorderable' => false, 'first' => false, 'last' => false])

{{--
    One line-item row for x-tallstack.reorderable-items-table. `data-item-row`
    is the DOM anchor the table's own drag/keyboard-move Alpine handlers
    read the live row order from (see that component's docblock) — every
    row MUST carry it, reorderable document or not, so a delete/re-add
    doesn't leave a stale id in the computed order.
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
    <td class="px-2 py-2">
        @if ($reorderable)
            <div class="flex items-center gap-1 text-gray-400">
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
        @endif
    </td>
    {{ $slot }}
</tr>
