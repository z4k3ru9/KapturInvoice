<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Jobs']]" title="Jobs">
        {{-- No "New job" button — a job is only ever created from an
             Accepted quotation (App\Actions\Sales\CreateSalesOrderFromQuotation),
             never a standalone create form here, matching
             SalesOrderResource's own getPages() (no create/edit page). --}}
    </x-tallstack.page-header>

    <div class="grid grid-cols-2 min-[820px]:!grid-cols-4 gap-2.5">
        <x-stats scope="compact" title="Total jobs" icon="briefcase" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['total'] }}</span>
            <x-slot:footer>All statuses</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Active" icon="arrow-path" color="amber">
            <span class="text-lg font-bold tabular-nums">{{ $stats['active'] }}</span>
            <x-slot:footer>Approved through handed over</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Draft" icon="pencil" color="gray">
            <span class="text-lg font-bold tabular-nums">{{ $stats['draft'] }}</span>
            <x-slot:footer>Not yet approved</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Closed" icon="check-circle" color="green">
            <span class="text-lg font-bold tabular-nums">{{ $stats['closed'] }}</span>
            <x-slot:footer>Operationally + financially closed</x-slot:footer>
        </x-stats>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" wire:click="filterStatus(null)"
                            class="px-2.5 py-1 rounded-md text-xs font-semibold {{ $status === null ? 'bg-[color:var(--ts-primary)] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}">
                        All
                    </button>
                    @foreach ($statuses as $case)
                        <button type="button" wire:click="filterStatus('{{ $case->value }}')"
                                class="px-2.5 py-1 rounded-md text-xs font-semibold {{ $status === $case->value ? 'bg-[color:var(--ts-primary)] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}">
                            {{ $case->getLabel() }}
                        </button>
                    @endforeach
                </div>

                <div class="w-full sm:w-64">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search number or client…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'number', 'label' => 'Number'],
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'job_type', 'label' => 'Type'],
            ['index' => 'approved_value', 'label' => 'Value', 'align' => 'right'],
            ['index' => 'next_milestone', 'label' => 'Next milestone'],
            ['index' => 'status', 'label' => 'Status'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$jobs" paginate loading>
            @interact('column_number', $row)
                <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200" title="{{ $row['number'] }}">
                    {{ \App\Support\TallStack\DocumentNumber::short($row['number']) }}
                </span>
            @endinteract

            @interact('column_next_milestone', $row)
                <div class="text-xs">
                    <div class="text-gray-700 dark:text-gray-200">{{ $row['next_milestone'] }}</div>
                    <div class="text-gray-400">{{ $row['next_milestone_due'] }}</div>
                </div>
            @endinteract

            @interact('column_status', $row)
                <div class="flex items-center gap-1.5">
                    <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm light />
                    @if ($row['closed'])
                        <x-icon name="lock-closed" class="w-3.5 h-3.5 text-gray-400 shrink-0" title="Fully closed" />
                    @endif
                </div>
            @endinteract

            @interact('column_actions', $row, $company)
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="eye" href="{{ route('tallstack.jobs.show', [$company, $row['id']]) }}" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Open" />
                    <x-button icon="document-arrow-down" href="{{ route('sales-orders.pdf', $row['id']) }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download PDF" />
                    {{-- Only ever shown for a Draft row — the guard's full
                         predicate (no invoices/deliveries/handovers/service
                         reports/job cost allocations/variations) is still
                         re-checked server-side by
                         App\Actions\Sales\ForceDeleteSalesOrder. --}}
                    @if ($row['status'] === \App\Enums\SalesOrderStatus::Draft)
                        <x-dropdown icon="ellipsis-vertical" scope="row-action">
                            <x-dropdown.items text="Force delete" icon="trash" wire:click="forceDelete({{ $row['id'] }})" wire:confirm="Permanently delete this job? This cannot be undone." />
                        </x-dropdown>
                    @endif
                </div>
            @endinteract

            <x-slot:empty>No jobs found.</x-slot:empty>
        </x-table>
    </x-card>
</div>
