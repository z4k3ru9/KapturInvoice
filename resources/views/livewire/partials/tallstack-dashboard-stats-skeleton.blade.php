{{--
    The 5 stat-card skeleton placeholders shared by both loading states in
    resources/views/livewire/tallstack-dashboard.blade.php (the very first
    paint, and any later wire:loading round-trip) — pulled into its own
    partial rather than repeated twice.

    Each card passes `icon`/`title` to match its own real counterpart's
    structure (no `footer` — the real cards show title + number only, see
    below) — TallStackUI's own stats skeleton
    (vendor/tallstackui/tallstackui/src/resources/views/components/stats/skeleton.blade.php)
    only draws a bar for a slot/prop that is actually present, so a bare
    `<x-stats skeleton />` with none of those renders an almost-empty box,
    not a placeholder card.

    Two rows, mirroring the real content below: the two currency (Rupiah)
    cards get their own wider row so a large value never fights a count
    card for space, the three plain-count cards keep the original tight
    grid. `col-span-full` lets each row-wrapper escape the parent's own
    `grid-cols-2/3/5` column track and lay out its own children instead —
    the parent's existing wire:loading.grid/wire:loading.remove.grid
    toggle classes are untouched.
--}}
<div class="col-span-full grid grid-cols-1 sm:grid-cols-2 gap-2.5">
    <x-stats scope="compact" skeleton icon="banknotes" title="Revenue" />
    <x-stats scope="compact" skeleton icon="clock" title="Outstanding" />
</div>
<div class="col-span-full grid grid-cols-3 gap-2.5">
    <x-stats scope="compact" skeleton icon="exclamation-triangle" title="Overdue" />
    <x-stats scope="compact" skeleton icon="document-text" title="Quotations" />
    <x-stats scope="compact" skeleton icon="briefcase" title="Jobs" />
</div>
