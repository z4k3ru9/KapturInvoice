<div class="max-w-[1400px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    {{--
        Shared page-header component — see its own docblock. "Dashboard"
        itself is a short, fixed title (unlike a record's name/number
        elsewhere), so an sm badge/status line is used here rather than
        the larger heading treatment a longer/variable title would need —
        leaving more of the row's width to the period/export/refresh
        controls on the right before they wrap.
    --}}
    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Overview']]" title="Dashboard">
        <x-slot:badge>
            <x-badge text="Owner · {{ auth()->user()->name }}" color="blue" icon="user-circle" sm />
            <span class="text-xs text-gray-400">Updated moments ago</span>
        </x-slot:badge>
        <x-slot:actions>
            <x-dropdown text="{{ $periodLabel }}" icon="calendar" scope="toolbar">
                @foreach ($periods as $value => $label)
                    <x-dropdown.items :text="$label" wire:click="$set('period', '{{ $value }}')" />
                @endforeach
            </x-dropdown>
            <x-button text="Export summary" icon="arrow-down-tray" sm color="gray" class="h-9" />
            <x-button icon="arrow-path" sm color="gray" scope="icon-action" class="h-9 w-9" wire:click="$refresh" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{--
        Phase 12 (onboarding/zero-state) — a dashboard panel, not a
        separate route: the Stitch "First-Run & Zero-State Onboarding
        (Axen Technology Variant)" mockup renders this content as the
        Dashboard's own top section (same sidebar/header chrome, same
        page), not a standalone page — see
        docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md. Reuses
        App\Filament\Support\SetupChecklist's exact five steps and "done"
        logic unmodified; the mockup itself shows four Axen-specific steps
        (legal entity/tax registry, catalog, client+quotation, a bank
        escrow VA) plus CSV import and a "contact Axen Finance" card — all
        deferred/out-of-scope features (payment gateway checkout, CSV
        import) this project's own CLAUDE.md already excludes from launch
        scope, so only the layout language (a progress-badged step-card
        row) is carried over, never those specific steps or actions. The
        panel hides itself once every step is done, exactly mirroring
        App\Filament\Widgets\SetupChecklistWidget::canView().
    --}}
    @if ($showChecklist)
        <x-card>
            <x-slot:header>
                <div class="flex flex-wrap items-center justify-between gap-2 w-full">
                    <div>
                        <div class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Workspace setup &amp; first-run guide</div>
                        <div class="text-xs text-gray-400">Complete these steps to start issuing invoices.</div>
                    </div>
                    <x-badge text="{{ $checklist['done'] }} of {{ $checklist['total'] }} completed" color="blue" sm icon="clipboard-document-check" />
                </div>
            </x-slot:header>

            {{--
                x-step's "panels" variation (TallStackUI's own Step/Panels
                component) replaces the previous hand-rolled card grid. Each
                step's full sentence-length SetupChecklist::for() label
                (e.g. "Set up your company profile and numbering") is too
                long for the panel nav strip's fixed-width title/badge row,
                so only a short summary shows there — the original full
                text moves into an <x-tooltip> in the step's own content
                panel below instead of being dropped. Keyed by the step's
                stable 'key' (SetupChecklist's own array key, not the
                array's 0-based index) so this map doesn't depend on step
                ordering.
            --}}
            @php
                $stepSummaries = [
                    'company_profile' => 'Company profile',
                    'numbering' => 'Invoice numbering',
                    'catalog' => 'Catalog',
                    'client' => 'Clients',
                    'quotation' => 'First quotation',
                ];
            @endphp
            <x-step panels navigate :selected="$firstIncompleteStep + 1">
                @foreach ($checklist['steps'] as $index => $step)
                    <x-step.items :step="$index + 1" :title="$stepSummaries[$step['key']] ?? $step['label']" :completed="$step['done']">
                        <div class="flex items-center justify-between gap-3 p-4">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium {{ $step['done'] ? 'text-gray-500 dark:text-gray-400 line-through' : 'text-gray-900 dark:text-gray-100' }}">
                                    {{ $stepSummaries[$step['key']] ?? $step['label'] }}
                                </p>
                                <x-tooltip :text="$step['label']" />
                            </div>
                            @if ($step['done'])
                                <x-badge text="Done" color="green" sm icon="check" />
                            @else
                                <x-button text="Go" icon="arrow-right" href="{{ $step['url'] }}" sm color="blue" />
                            @endif
                        </div>
                    </x-step.items>
                @endforeach
            </x-step>
        </x-card>
    @endif

    {{-- Stat row --}}
    {{--
        The `!` (important) modifiers on the wider breakpoints work around
        the same cross-stylesheet cascade quirk noted in
        components/tallstack/app.blade.php: TallStackUI's own compiled CSS
        (loaded after app.css) redeclares an unconditional `.grid-cols-2`
        that otherwise outranks these responsive classes at any width.
    --}}
    <div class="grid grid-cols-2 min-[820px]:!grid-cols-3 min-[1180px]:!grid-cols-5 gap-2.5">
        {{--
            The trend arrow used to sit via the stats component's own
            increase/decrease slot, in the same tight flex row as the
            Rupiah amount — that row's fixed content (icon square + a
            ~13-character amount + the arrow) is wider than the card at
            this density, and nothing in it shrinks, so the arrow spilled
            out past the card's right edge instead of clipping to it.
            Building the title line by hand instead — no `title` prop, no
            increase/decrease — puts the arrow next to the much shorter
            "Total revenue" label, where the row has real room to spare.
        --}}
        <x-stats scope="compact" icon="banknotes">
            <div class="flex items-center gap-1">
                <span class="dark:text-dark-300 text-xs text-gray-600">Total revenue</span>
                @if ($stats['revenueUp'] && ! $stats['revenueFlat'])
                    <x-icon name="arrow-trending-up" class="h-3 w-3 text-green-500 shrink-0" />
                @elseif (! $stats['revenueUp'] && ! $stats['revenueFlat'])
                    <x-icon name="arrow-trending-down" class="h-3 w-3 text-red-500 shrink-0" />
                @endif
            </div>
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

    {{--
        Phase 12: a company with no clients and no quotations yet has
        nothing meaningful to chart (an all-zero flatline reads as broken,
        not "new"), so it gets a deliberate welcome panel here instead of
        the trend chart — matching the Stitch mockup's "No Active Jobs or
        Commercial Invoices" placement (it replaces the main content area,
        not just a table's own empty-row text). A populated company keeps
        the existing chart unchanged.
    --}}
    @if ($isZeroState)
        <x-card>
            <div class="flex flex-col items-center justify-center gap-3 py-10 text-center">
                <div class="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-blue-50 dark:bg-blue-950">
                    <x-icon name="briefcase" class="h-7 w-7 text-blue-500" />
                </div>
                <div>
                    <p class="font-semibold text-gray-900 dark:text-gray-100">No jobs or invoices yet</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-gray-400">
                        Your transactional queue is clean. Add a client and send your first
                        quotation — once it's accepted, it becomes a job ready to bill.
                    </p>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <x-button text="Create your first quotation" icon="plus" href="{{ route('tallstack.quotations.create', $company) }}" color="blue" sm />
                    <x-button text="Add a client" icon="user-plus" href="/admin/{{ $company->slug }}/clients" color="gray" sm />
                </div>
            </div>
        </x-card>
    @else
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
    @endif

    {{-- Expiring quotations + action queue --}}
    <div class="grid lg:grid-cols-[1.4fr_1fr] gap-4 items-start">
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between w-full">
                    <div>
                        <div class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Expiring quotations</div>
                        <div class="text-xs text-gray-400">{{ $expiring->count() }} due for a decision within 7 days</div>
                    </div>
                    <x-button text="View all" href="{{ route('tallstack.quotations', $company) }}" color="blue" sm />
                </div>
            </x-slot:header>

            <x-table :headers="[
                ['index' => 'number', 'label' => 'Number'],
                ['index' => 'client', 'label' => 'Client'],
                ['index' => 'days', 'label' => 'Status'],
                ['index' => 'total', 'label' => 'Total'],
                ['index' => 'actions', 'label' => ''],
            ]" :rows="$expiring">
                {{-- Dense overview list — see the same pattern's own comment
                     on the Quotations register table. --}}
                @interact('column_number', $row)
                    <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200" title="{{ $row['number'] }}">
                        {{ \App\Support\TallStack\DocumentNumber::short($row['number']) }}
                    </span>
                @endinteract
                @interact('column_days', $row)
                    @if ($row['expired'])
                        <x-badge text="Expired {{ $row['days'] }}d ago" color="red" sm />
                    @else
                        <x-badge text="Expiring in {{ $row['days'] }}d" color="{{ $row['days'] <= 2 ? 'red' : 'amber' }}" sm />
                    @endif
                @endinteract
                @interact('column_actions', $row, $company)
                    <div class="flex items-center justify-end gap-2">
                        <x-button icon="eye" href="{{ route('tallstack.quotations.edit', [$company, $row['id']]) }}" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Review" />
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
                                <x-button text="View" href="{{ $item['url'] }}" color="blue" sm class="shrink-0" />
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</div>
