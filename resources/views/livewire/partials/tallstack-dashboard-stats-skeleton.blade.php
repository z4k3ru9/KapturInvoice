{{--
    The 5 stat-card skeleton placeholders shared by both loading states in
    resources/views/livewire/tallstack-dashboard.blade.php (the very first
    paint, and any later wire:loading round-trip) — pulled into its own
    partial rather than repeated twice.

    Each card passes `icon`/`title`/(a non-empty) `footer` to match its own
    real counterpart's structure — TallStackUI's own stats skeleton
    (vendor/tallstackui/tallstackui/src/resources/views/components/stats/skeleton.blade.php)
    only draws a bar for a slot/prop that is actually present, so a bare
    `<x-stats skeleton />` with none of those renders an almost-empty box,
    not a placeholder card.
--}}
<x-stats scope="compact" skeleton icon="banknotes" title="Total revenue">
    <x-slot:footer></x-slot:footer>
</x-stats>
<x-stats scope="compact" skeleton icon="clock" title="Outstanding balance">
    <x-slot:footer></x-slot:footer>
</x-stats>
<x-stats scope="compact" skeleton icon="exclamation-triangle" title="Overdue invoices">
    <x-slot:footer></x-slot:footer>
</x-stats>
<x-stats scope="compact" skeleton icon="document-text" title="Open quotations">
    <x-slot:footer></x-slot:footer>
</x-stats>
<x-stats scope="compact" skeleton icon="briefcase" title="Active jobs">
    <x-slot:footer></x-slot:footer>
</x-stats>
