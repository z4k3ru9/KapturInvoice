<div class="max-w-[1400px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Handover Reports']]" title="Handover Reports" />

    {{-- Stat row — same "compact" x-stats convention as the Delivery
         Orders register beside it. No status badge column below either:
         a HandoverReport is only ever written, complete, through
         App\Actions\Delivery\CompleteHandover — there is no draft/pending
         handover to filter by, only whether it was an Owner/Admin
         override. --}}
    <div class="grid grid-cols-2 min-[820px]:!grid-cols-4 gap-2.5">
        <x-stats scope="compact" title="Total handovers" icon="document-check" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['total'] }}</span>
            <x-slot:footer>All recorded handovers</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="This month" icon="calendar-days" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['thisMonth'] }}</span>
            <x-slot:footer>Handed over so far this month</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Overrides" icon="exclamation-triangle" color="amber">
            <span class="text-lg font-bold tabular-nums">{{ $stats['overrides'] }}</span>
            <x-slot:footer>Owner/Admin completeness override</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Jobs served" icon="briefcase" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['distinctJobs'] }}</span>
            <x-slot:footer>Distinct jobs with a handover</x-slot:footer>
        </x-stats>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">All handover reports</span>
                <div class="w-full sm:w-64">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search number, job or client…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'number', 'label' => 'Number'],
            ['index' => 'job_number', 'label' => 'Job'],
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'handover_date', 'label' => 'Date'],
            ['index' => 'is_override', 'label' => 'Override'],
            ['index' => 'created_by', 'label' => 'Recorded by'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$handoverReports" paginate loading>
            @interact('column_number', $row)
                <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200" title="{{ $row['number'] }}">
                    {{ \App\Support\TallStack\DocumentNumber::short($row['number']) }}
                </span>
            @endinteract

            @interact('column_job_number', $row, $company)
                @if ($row['job_id'])
                    <a href="{{ route('tallstack.jobs.show', [$company, $row['job_id']]) }}" class="text-xs font-medium text-[color:var(--ts-primary)] hover:underline">
                        {{ $row['job_number'] }}
                    </a>
                @else
                    <span class="text-xs text-gray-400">—</span>
                @endif
            @endinteract

            @interact('column_is_override', $row)
                @if ($row['is_override'])
                    <span title="{{ $row['override_reason'] }}">
                        <x-badge text="Override" color="amber" sm />
                    </span>
                @else
                    <span class="text-xs text-gray-400">—</span>
                @endif
            @endinteract

            @interact('column_actions', $row, $company)
                <div class="flex items-center justify-end gap-2">
                    @if ($row['job_id'])
                        <x-button icon="briefcase" href="{{ route('tallstack.jobs.show', [$company, $row['job_id']]) }}" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Open job" />
                    @endif
                    <x-button icon="document-arrow-down" href="{{ route('handover-reports.pdf', $row['id']) }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download PDF" />
                </div>
            @endinteract

            <x-slot:empty>No handover reports recorded yet.</x-slot:empty>
        </x-table>
    </x-card>
</div>
