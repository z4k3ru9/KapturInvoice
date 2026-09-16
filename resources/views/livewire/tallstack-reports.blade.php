<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Reports']]" title="Financial Analytics & Tax Reports">
        <x-slot:actions>
            <x-dropdown text="{{ $periodLabel }}" icon="calendar" scope="toolbar">
                @foreach ($periods as $value => $label)
                    <x-dropdown.items :text="$label" wire:click="$set('period', '{{ $value }}')" />
                @endforeach
            </x-dropdown>
            {{-- Decorative, matching the Dashboard's own "Export summary"
                 button — no export pipeline exists anywhere in this
                 codebase to wire this to. --}}
            <x-button text="Export report" icon="arrow-down-tray" sm color="gray" class="h-9" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Financial Analytics --------------------------------------------
         grid-cols-1 below `sm` (not grid-cols-2 from the smallest width
         up): at ~132px per card on a 375px phone, the bold currency
         number ("Rp 94.000.000") has no room and its `overflow: visible`
         wrapper (TallStackUI's own `wrapper.first`, not ours to change)
         let it bleed straight into the neighboring card's icon — visually
         broken, confirmed live at mobile width. One column at phone width
         gives each card its full ~343px to render the number without
         truncating; `truncate` on the number span is a second line of
         defense for an even narrower device or a larger real balance. --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 min-[820px]:!grid-cols-3 gap-2.5">
        <x-stats scope="compact" title="Total revenue" icon="banknotes">
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['revenue'] }}</span>
            <x-slot:footer>{{ $periodLabel }} · cash collected</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Outstanding balance" icon="clock" color="amber">
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['outstanding'] }}</span>
            <x-slot:footer>{{ $stats['outstandingCount'] }} invoice{{ $stats['outstandingCount'] === 1 ? '' : 's' }}</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Overdue amount" icon="exclamation-triangle" color="red">
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['overdueTotal'] }}</span>
            <x-slot:footer>{{ $stats['overdueCount'] }} overdue</x-slot:footer>
        </x-stats>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between w-full">
                <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Revenue &amp; cash inflow trend</span>
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
    </x-card>

    {{-- Job margin --------------------------------------------------------
         Three genuinely separate columns (sales value / allocated gross
         cost / unallocated purchasing cost) rather than one blended
         "cost" number — per FINALIZED-DECISIONS.md §3 and this project's
         own standing rule, mirroring the equivalent report widget from the
         pre-TallStackUI Filament admin exactly. --}}
    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between w-full">
                <div>
                    <div class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Job margin</div>
                    <div class="text-xs text-gray-400">Sales value, allocated gross cost, and unallocated purchasing cost per job</div>
                </div>
                <x-button text="View jobs" href="{{ route('tallstack.jobs', $company) }}" color="blue" sm />
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'number', 'label' => 'Job'],
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'status', 'label' => 'Status'],
            ['index' => 'sales_value', 'label' => 'Sales value', 'align' => 'right'],
            ['index' => 'allocated_cost', 'label' => 'Allocated gross cost', 'align' => 'right'],
            ['index' => 'margin', 'label' => 'Margin', 'align' => 'right'],
            ['index' => 'unallocated_cost', 'label' => 'Unallocated purchasing cost', 'align' => 'right'],
        ]" :rows="$jobMargins">
            @interact('column_number', $row, $company)
                <a href="{{ route('tallstack.jobs.show', [$company, $row['id']]) }}" class="font-mono text-xs font-medium text-[color:var(--ts-primary)] hover:underline">
                    {{ $row['number'] }}
                </a>
            @endinteract
            @interact('column_status', $row)
                <x-badge :text="$row['status']" :color="$row['status_color']" sm light />
            @endinteract
            @interact('column_margin', $row)
                <span class="tabular-nums {{ $row['margin_negative'] ? 'text-red-600 dark:text-red-400! font-semibold' : 'text-green-600 dark:text-green-400!' }}">
                    {{ $row['margin'] }}
                </span>
            @endinteract
            @interact('column_unallocated_cost', $row)
                <span class="tabular-nums text-amber-600 dark:text-amber-400!">{{ $row['unallocated_cost'] }}</span>
            @endinteract
            <x-slot:empty>No jobs with billed invoices yet.</x-slot:empty>
        </x-table>
    </x-card>

    {{-- Tax Reports ---------------------------------------------------- --}}
    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between w-full">
                <div>
                    <div class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Tax reports</div>
                    <div class="text-xs text-gray-400">Filed and pending e-Faktur / tax recap entries</div>
                </div>
            </div>
        </x-slot:header>

        @if (! $taxEnabled)
            {{-- Mirrors tallstack-settings-lookups.blade.php's own
                 tax-disabled empty state — same wording pattern, same
                 icon-plus-two-line layout, "explain why, don't just look
                 broken" (this project's own established convention). --}}
            <div class="flex flex-col items-center justify-center text-center py-14 px-6">
                <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800! grid place-items-center mb-3">
                    <x-icon name="receipt-percent" class="w-6 h-6 text-gray-400" />
                </div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200!">Tax is disabled for this company</p>
                <p class="text-xs text-gray-400 mt-1 max-w-sm">No tax recaps are generated — invoices issued here never carry tax, so there is nothing to report.</p>
            </div>
        @else
            <x-table :headers="[
                ['index' => 'number', 'label' => 'Recap #'],
                ['index' => 'client', 'label' => 'Client'],
                ['index' => 'reporting_period', 'label' => 'Period'],
                ['index' => 'status_label', 'label' => 'Status'],
                ['index' => 'total', 'label' => 'Tax amount', 'align' => 'right'],
                ['index' => 'filing_date', 'label' => 'Filed'],
                ['index' => 'actions', 'label' => ''],
            ]" :rows="$taxRecaps">
                @interact('column_status_label', $row)
                    <x-badge :text="$row['status_label']" :color="$row['status_color']" sm light />
                @endinteract
                @interact('column_actions', $row)
                    <div class="flex items-center justify-end">
                        <x-button icon="document-arrow-down" sm color="gray" scope="icon-action" class="h-9 w-9" href="{{ $row['pdf_url'] }}" target="_blank" tooltip="Download PDF" />
                    </div>
                @endinteract
                <x-slot:empty>No tax recaps have been generated yet — issuing a taxable invoice creates one automatically.</x-slot:empty>
            </x-table>
        @endif
    </x-card>
</div>
