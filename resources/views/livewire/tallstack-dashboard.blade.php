<div class="max-w-[1400px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    {{-- Page header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-400 mb-1">
                <span>{{ $company->name }}</span><span>/</span><span>Overview</span>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="font-bold text-2xl text-gray-900 dark:text-gray-100">Dashboard</h1>
                <x-badge text="Owner · {{ auth()->user()->name }}" color="blue" icon="user-circle" />
                <span class="text-xs text-gray-400">Updated moments ago</span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <x-dropdown text="{{ $periodLabel }}" icon="calendar" scope="toolbar">
                @foreach ($periods as $value => $label)
                    <x-dropdown.items :text="$label" wire:click="$set('period', '{{ $value }}')" />
                @endforeach
            </x-dropdown>
            <x-button text="Export summary" icon="arrow-down-tray" sm color="gray" class="h-9" />
            <x-button icon="arrow-path" square sm color="gray" class="h-9 w-9" wire:click="$refresh" />
        </div>
    </div>

    {{-- Stat row --}}
    {{--
        The `!` (important) modifiers on the wider breakpoints work around
        the same cross-stylesheet cascade quirk noted in
        components/tallstack/app.blade.php: TallStackUI's own compiled CSS
        (loaded after app.css) redeclares an unconditional `.grid-cols-2`
        that otherwise outranks these responsive classes at any width.
    --}}
    <div class="grid grid-cols-2 min-[820px]:!grid-cols-3 min-[1180px]:!grid-cols-5 gap-2.5">
        <x-stats scope="compact" title="Total revenue" icon="banknotes" :increase="$stats['revenueUp'] && !$stats['revenueFlat']" :decrease="!$stats['revenueUp'] && !$stats['revenueFlat']">
            <span class="text-lg font-bold tabular-nums">{{ $stats['revenue'] }}</span>
            <x-slot:footer>{{ $periodLabel }}</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Outstanding balance" icon="clock" color="amber">
            <span class="text-lg font-bold tabular-nums">{{ $stats['outstanding'] }}</span>
            <x-slot:footer>{{ $stats['outstandingCount'] }} invoice{{ $stats['outstandingCount'] === 1 ? '' : 's' }}</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Overdue invoices" icon="exclamation-triangle" color="red">
            <span class="text-lg font-bold tabular-nums">{{ $stats['overdueCount'] }}</span>
            <x-slot:footer><span class="text-red-600 dark:text-red-400">{{ $stats['overdueTotal'] }} overdue</span></x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Open quotations" icon="document-text" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['openQuotations'] }}</span>
            <x-slot:footer>Approved or sent</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Active jobs" icon="briefcase" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['activeJobs'] }}</span>
            <x-slot:footer>In progress</x-slot:footer>
        </x-stats>
    </div>

    {{-- Trend chart --}}
    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between w-full">
                <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Revenue &amp; cash inflow trend</span>
            </div>
        </x-slot:header>

        <x-chart
            type="area"
            :labels="$chartLabels"
            :series="[
                ['name' => 'Invoiced (legacy)', 'data' => $chartInvoiced],
                ['name' => 'Cash collected', 'data' => $chartCollected],
            ]"
            :colors="['red', 'primary']"
            legend
            height="280"
        />

        <x-slot:footer>
            <button type="button" class="text-xs font-semibold text-[color:var(--ts-primary)]">View as accessible table</button>
        </x-slot:footer>
    </x-card>

    {{-- Expiring quotations + action queue --}}
    <div class="grid lg:grid-cols-[1.4fr_1fr] gap-4 items-start">
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between w-full">
                    <div>
                        <div class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Expiring quotations</div>
                        <div class="text-xs text-gray-400">{{ $expiring->count() }} due for a decision within 7 days</div>
                    </div>
                    <x-button text="View all" href="/admin/{{ $company->slug }}/quotations" color="primary" sm />
                </div>
            </x-slot:header>

            <x-table :headers="[
                ['index' => 'number', 'label' => 'Number'],
                ['index' => 'client', 'label' => 'Client'],
                ['index' => 'days', 'label' => 'Status'],
                ['index' => 'total', 'label' => 'Total'],
                ['index' => 'actions', 'label' => ''],
            ]" :rows="$expiring">
                @interact('column_days', $row)
                    @if ($row['expired'])
                        <x-badge text="Expired {{ $row['days'] }}d ago" color="red" sm />
                    @else
                        <x-badge text="Expiring in {{ $row['days'] }}d" color="{{ $row['days'] <= 2 ? 'red' : 'amber' }}" sm />
                    @endif
                @endinteract
                @interact('column_actions', $row, $company)
                    <div class="flex items-center justify-end gap-2">
                        <x-button icon="eye" href="/admin/{{ $company->slug }}/quotations/{{ $row['id'] }}" square sm color="gray" class="h-9 w-9" tooltip="Review" />
                        <x-dropdown icon="ellipsis-vertical" scope="row-action">
                            <x-dropdown.items text="Extend 7 days" icon="calendar" />
                            <x-dropdown.items text="Void quotation" icon="x-circle" />
                        </x-dropdown>
                    </div>
                @endinteract
                <x-slot:empty>No quotations expiring soon.</x-slot:empty>
            </x-table>
        </x-card>

        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between w-full">
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Action queue</span>
                    <x-badge text="{{ count($actionQueue) }} items" color="red" sm />
                </div>
            </x-slot:header>

            @if (empty($actionQueue))
                <p class="text-sm text-gray-400">Nothing needs your attention.</p>
            @else
                <ul class="flex flex-col gap-3">
                    @foreach ($actionQueue as $item)
                        <li class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0 bg-{{ $item['tone'] === 'danger' ? 'red' : 'amber' }}-500"></span>
                                <span class="text-sm truncate text-gray-700 dark:text-gray-200">{{ $item['count'] }} {{ $item['label'] }}</span>
                            </div>
                            @if ($item['url'])
                                <x-button text="View" href="{{ $item['url'] }}" color="primary" sm class="shrink-0" />
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</div>
