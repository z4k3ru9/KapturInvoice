<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    {{-- Full-page overlay for the refresh icon button's wire:click="$refresh" below --}}
    <x-loading loading="$refresh" delay="short" />

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
            {{--
                Calls loadDashboardData() directly (not $refresh) — since
                the heavy stats/chart query is no longer run from render(),
                a bare $refresh would just re-render the page with
                whatever was already loaded, not actually pull fresh
                numbers.
            --}}
            <x-button icon="arrow-path" sm color="gray" scope="icon-action" class="h-9 w-9" wire:click="loadDashboardData" tooltip="Refresh" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{--
        Phase 12 (onboarding/zero-state) — a dashboard panel, not a
        separate route: the Stitch "First-Run & Zero-State Onboarding
        (Axen Technology Variant)" mockup renders this content as the
        Dashboard's own top section (same sidebar/header chrome, same
        page), not a standalone page — see
        docs/rebuild/outputs/ui-rebuild/25-tallstack-full-rebuild-plan.md. Reuses
        App\Support\Dashboard\SetupChecklist's exact five steps and "done"
        logic unmodified; the mockup itself shows four Axen-specific steps
        (legal entity/tax registry, catalog, client+quotation, a bank
        escrow VA) plus CSV import and a "contact Axen Finance" card — all
        deferred/out-of-scope features (payment gateway checkout, CSV
        import) this project's own CLAUDE.md already excludes from launch
        scope, so only the layout language (a progress-badged step-card
        row) is carried over, never those specific steps or actions. The
        panel hides itself once every step is done (`$showChecklist`).
    --}}
    @if ($showChecklist)
        <x-card>
            <x-slot:header>
                <div class="flex flex-wrap items-center justify-between gap-2 w-full">
                    <div>
                        <div class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Workspace setup &amp; first-run guide</div>
                        <div class="text-xs text-gray-400">Complete these steps to start issuing invoices.</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-badge text="{{ $checklist['done'] }} of {{ $checklist['total'] }} completed" color="blue" sm icon="clipboard-document-check" />
                        {{-- color="brand" — Primary role: the main action
                             of this onboarding panel. --}}
                        <x-button text="Go" icon="arrow-right" href="{{ $checklist['steps'][$firstIncompleteStep]['url'] }}" sm color="brand" />
                    </div>
                </div>
            </x-slot:header>

            {{--
                x-step's "panels" variation (TallStackUI's own Step/Panels
                component) replaces the previous hand-rolled card grid. The
                nav strip already shows each step's short summary as its
                active/highlighted title (SetupChecklist's own 'key',
                mapped below — its real label is a full sentence, e.g.
                "Set up your company profile and numbering", too long for
                that fixed-width row), so the content panel below just
                shows that full sentence directly — no need to repeat the
                short title or hide the sentence behind a tooltip. A single
                "Go" action lives in the header next to the progress badge
                instead of one per step, since it always points at the
                same next actionable step regardless of which panel is
                being viewed.
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
                        <div class="p-4">
                            <p class="text-sm font-medium {{ $step['done'] ? 'text-gray-500 dark:text-gray-400 line-through' : 'text-gray-900 dark:text-gray-100' }}">
                                {{ $step['label'] }}
                            </p>
                        </div>
                    </x-step.items>
                @endforeach
            </x-step>
        </x-card>
    @endif

    {{--
        Stat row + trend chart — both wrapped in one lazy-loading section.
        `wire:init="loadDashboardData"` fires a SEPARATE Livewire
        round-trip right after the first paint (see
        App\Livewire\TallStackDashboard's own docblock): the initial HTTP
        response never waits on RevenueBuckets::forPeriod()'s per-bucket
        query loop, it just paints the skeleton pair below immediately.

        Each section below renders as an ADJACENT PAIR of blocks — one
        `wire:loading`-shown skeleton, one `wire:loading.remove`-shown real
        block — rather than a single block toggling its own `skeleton`
        prop from PHP. TallStackUI's `skeleton` prop is baked into the
        server-rendered HTML, so it cannot react to an in-flight request by
        itself; pairing it with `wire:loading`/`wire:loading.remove` is
        what makes the skeleton genuinely reappear on every subsequent
        round-trip this component makes (a period change, the header's
        refresh button), not just the very first load. Before the
        component has loaded at all (`$statsLoaded` false, `wire:loading`
        not yet active), the "real" block itself falls back to skeleton
        cards too, so there is never a gap with neither block visible.
    --}}
    <div wire:init="loadDashboardData">
        {{--
            The `!` (important) modifiers on the wider breakpoints work
            around the same cross-stylesheet cascade quirk noted in
            components/tallstack/app.blade.php: TallStackUI's own compiled
            CSS (loaded after app.css) redeclares an unconditional
            `.grid-cols-2` that otherwise outranks these responsive
            classes at any width.
        --}}
        <div wire:loading.grid class="hidden grid-cols-2 min-[820px]:!grid-cols-3 min-[1180px]:!grid-cols-5 gap-2.5">
            @include('livewire.partials.tallstack-dashboard-stats-skeleton')
        </div>
        <div wire:loading.remove.grid class="grid grid-cols-2 min-[820px]:!grid-cols-3 min-[1180px]:!grid-cols-5 gap-2.5">
            @if ($statsLoaded)
                {{--
                    The trend arrow used to sit via the stats component's
                    own increase/decrease slot, in the same tight flex row
                    as the Rupiah amount — that row's fixed content (icon
                    square + a ~13-character amount + the arrow) is wider
                    than the card at this density, and nothing in it
                    shrinks, so the arrow spilled out past the card's right
                    edge instead of clipping to it. Building the title
                    line by hand instead — no `title` prop, no
                    increase/decrease — puts the arrow next to the much
                    shorter "Total revenue" label, where the row has real
                    room to spare. Re-verified against TallStackUI 4.1's
                    real `increase`/`decrease` markup (vendor/tallstackui/
                    tallstackui/src/resources/views/components/stats/main.blade.php):
                    that slot is still a plain, un-shrinkable div sitting
                    after the `grow` title/number column, so it still
                    overflows this card's width today — the hand-rolled
                    arrow stays.

                    The revenue card also gets a subtle sparkline (the
                    period's cash-collected trend) via TallStackUI's own
                    `chart` slot — the same series the trend chart below
                    plots, just behind the headline number at low opacity
                    (the package's own default `chart.wrapper` styling).
                --}}
                <x-stats scope="compact" icon="banknotes">
                    <x-slot:chart>
                        <x-chart :series="$chartCollected" color="{{ $stats['revenueUp'] && ! $stats['revenueFlat'] ? 'green' : 'red' }}" curve="smooth" :height="64" />
                    </x-slot:chart>
                    <div class="flex items-center gap-1">
                        <span class="text-xs text-gray-600 dark:text-gray-300!">Total revenue</span>
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

                {{--
                    :number/animated (not a slot) on these three count
                    cards — short 1s count-up, tasteful rather than
                    gimmicky for a billing admin. The two currency cards
                    above stay pre-formatted Rupiah strings in a slot
                    (animate only works against a raw number — mid-count a
                    plain digit string like "45231000" has no thousands
                    separator, which read worse than the existing static
                    Money::format() convention for the app's headline
                    amounts).
                --}}
                <x-stats scope="compact" title="Overdue invoices" icon="exclamation-triangle" color="red" :number="$stats['overdueCount']" animated :duration="1">
                    <x-slot:footer><span class="text-red-600 dark:text-red-400">{{ $stats['overdueTotal'] }} overdue</span></x-slot:footer>
                </x-stats>

                <x-stats scope="compact" title="Open quotations" icon="document-text" color="blue" :number="$stats['openQuotations']" animated :duration="1">
                    <x-slot:footer>Approved or sent</x-slot:footer>
                </x-stats>

                <x-stats scope="compact" title="Active jobs" icon="briefcase" color="blue" :number="$stats['activeJobs']" animated :duration="1">
                    <x-slot:footer>In progress</x-slot:footer>
                </x-stats>
            @else
                @include('livewire.partials.tallstack-dashboard-stats-skeleton')
            @endif
        </div>
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
                    {{-- color="brand" — Primary role: the zero-state's own
                         main call to action; "Add a client" (gray, Neutral)
                         stays visually distinct beside it. --}}
                    <x-button text="Create your first quotation" icon="plus" href="{{ route('tallstack.quotations.create', $company) }}" color="brand" sm />
                    <x-button text="Add a client" icon="user-plus" href="{{ route('tallstack.clients', $company) }}" color="gray" sm />
                </div>
            </div>
        </x-card>
    @else
        {{--
            Trend chart — same lazy-loading pair as the stat row above,
            sharing the parent's `wire:init="loadDashboardData"`.
        --}}
        {{--
            x-data drives the "View as accessible table" toggle in the
            footer below — previously a dead `<button>` with no click
            handler at all (found during the dark-mode/TallStackUI audit).
            Alpine-only (no Livewire round-trip needed): both
            representations are already rendered server-side from the same
            $chartLabels/$chartInvoiced/$chartCollected buckets
            (`App\Support\Dashboard\RevenueBuckets`), so toggling is just
            a visibility swap.

            This has to be a plain wrapping `<div>`, not an `x-data`
            attribute on `<x-card>` itself: TallStackUI's own Card
            component (vendor/tallstackui/tallstackui/src/resources/views/
            components/card/main.blade.php) already sets its own
            `x-data="tallstackui_card(...)"` on its root element and only
            forwards `$attributes->whereStartsWith('x-on:')` — any other
            attribute passed to `<x-card>`, `x-data` included, is silently
            dropped, never reaching the DOM. Passing `x-data` directly on
            `<x-card>` was tried first and produced a real, reproduced
            "showTrendTable is not defined" Alpine error in the browser
            console — the header/body/footer slots rendered with no
            ancestor element carrying that scope at all.
        --}}
        <div x-data="{ showTrendTable: false }">
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between w-full">
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Revenue &amp; cash inflow trend</span>
                </div>
            </x-slot:header>

            <div wire:loading.block class="hidden">
                <x-chart skeleton height="280" />
            </div>
            <div wire:loading.remove.block>
                @if ($statsLoaded)
                    {{--
                        `grid`/`tooltip`/`markers` turned on (previously
                        only `legend`) so the two overlapping area series
                        are actually readable at a glance instead of
                        needing a hover-only tooltip with no gridlines to
                        judge magnitude against. `curve="smooth"` reads
                        better than the default straight-segment join for
                        a monthly/daily revenue trend (real day-to-day
                        invoicing/collection swings, not a precise
                        instrument reading — smoothing doesn't misrepresent
                        it the way it would on e.g. a stock chart).
                        `:formatter` reuses the exact same
                        `App\Support\Dashboard\Money::format()` the stat
                        cards already use, so the axis/tooltip Rupiah
                        formatting (thousands separator, no decimals)
                        matches the rest of the page instead of the
                        component's own generic `number_format()` default.
                    --}}
                    <div x-show="!showTrendTable">
                        <x-chart
                            type="area"
                            :labels="$chartLabels"
                            :series="[
                                ['name' => 'Invoiced (legacy)', 'data' => $chartInvoiced],
                                ['name' => 'Cash collected', 'data' => $chartCollected],
                            ]"
                            :colors="['red', 'primary']"
                            grid
                            legend
                            tooltip
                            markers
                            curve="smooth"
                            :formatter="fn (float $value) => \App\Support\Dashboard\Money::format($value, $currency)"
                            height="280"
                        />
                    </div>
                    {{--
                        The accessible alternative to the chart above — same
                        three buckets, one row per label, as a real table
                        rather than something a screen reader has to
                        interpret from an SVG. `x-cloak` (styled by
                        TallStackUI's own compiled CSS, `[x-cloak]{display:
                        none}`) avoids a flash of the table before Alpine
                        initializes.
                    --}}
                    <div x-show="showTrendTable" x-cloak id="dashboard-trend-table">
                        <x-table :headers="[
                            ['index' => 'period', 'label' => 'Period'],
                            ['index' => 'invoiced', 'label' => 'Invoiced (legacy)'],
                            ['index' => 'collected', 'label' => 'Cash collected'],
                        ]" :rows="$chartRows">
                            <x-slot:empty>No revenue data for this period.</x-slot:empty>
                        </x-table>
                    </div>
                @else
                    <x-chart skeleton height="280" />
                @endif
            </div>

            <x-slot:footer>
                <x-button flat xs color="blue" x-on:click="showTrendTable = !showTrendTable" x-bind:aria-expanded="showTrendTable.toString()" aria-controls="dashboard-trend-table">
                    <span x-text="showTrendTable ? 'View as chart' : 'View as accessible table'"></span>
                </x-button>
            </x-slot:footer>
        </x-card>
        </div>
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
