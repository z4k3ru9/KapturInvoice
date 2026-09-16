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
                <x-tallstack.filter-dropdown
                    label="{{ $status === null ? 'All statuses' : collect($statuses)->firstWhere('value', $status)?->getLabel() }}"
                    :options="collect([['value' => null, 'label' => 'All statuses']])->concat(collect($statuses)->map(fn ($case) => ['value' => $case->value, 'label' => $case->getLabel()]))"
                    :active="$status"
                    method="filterStatus"
                />

                <div class="w-full sm:w-64">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search number or client…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        {{-- Sortable: number and client (joined on `clients.name` in
             TallStackSalesOrders::render()) — the two requested dimensions
             this page actually has a column for; there is no visible date
             column here (the default sort is by created_at, which isn't
             displayed). next_milestone is a computed display value with no
             matching database column and job_type/approved_value/status
             weren't asked for, so all four are marked sortable => false
             explicitly rather than left to the component's own
             sortable-by-default behavior. --}}
        <x-table :headers="[
            ['index' => 'number', 'label' => 'Number'],
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'job_type', 'label' => 'Type', 'sortable' => false],
            ['index' => 'approved_value', 'label' => 'Value', 'align' => 'right', 'sortable' => false],
            ['index' => 'next_milestone', 'label' => 'Next milestone', 'sortable' => false],
            ['index' => 'status', 'label' => 'Status', 'sortable' => false],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$jobs" :sort="$sort" striped :filter="['quantity' => 'quantity']" paginate loading>
            @interact('column_number', $row)
                <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200!" title="{{ $row['number'] }}">
                    {{ \App\Support\TallStack\DocumentNumber::short($row['number']) }}
                </span>
            @endinteract

            @interact('column_next_milestone', $row)
                <div class="text-xs">
                    <div class="text-gray-700 dark:text-gray-200!">{{ $row['next_milestone'] }}</div>
                    <div class="text-gray-400">{{ $row['next_milestone_due'] }}</div>
                </div>
            @endinteract

            @interact('column_status', $row)
                <div class="flex items-center gap-1.5">
                    <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm light />
                    @if ($row['closed'])
                        <x-icon name="lock-closed" class="w-3.5 h-3.5 text-gray-400 shrink-0" title="Fully closed" />
                    @endif
                    {{-- Hold is the override lever for the auto-close-
                         operationally automation (App\Actions\Delivery\
                         CompleteDelivery/CompleteHandover) — see
                         App\Models\Concerns\Holdable's own docblock. --}}
                    @if ($row['is_held'])
                        <x-icon name="pause-circle" class="w-3.5 h-3.5 text-amber-500" title="On hold: {{ $row['held_reason'] }}" />
                    @endif
                </div>
            @endinteract

            @interact('column_actions', $row, $company)
                @php
                    $extraActions = [];
                    // Only ever shown for a Draft row — the guard's full
                    // predicate (no invoices/deliveries/handovers/service
                    // reports/job cost allocations/variations) is still
                    // re-checked server-side by App\Actions\Sales\ForceDeleteSalesOrder.
                    if ($row['status'] === \App\Enums\SalesOrderStatus::Draft) {
                        $extraActions[] = ['text' => 'Force delete', 'icon' => 'trash', 'color' => 'red', 'click' => 'forceDelete('.$row['id'].')', 'confirm' => 'Permanently delete this job? This cannot be undone.'];
                    }
                    $extraActions[] = $row['is_held']
                        ? ['text' => 'Release hold', 'icon' => 'play-circle', 'click' => 'releaseHold('.$row['id'].')']
                        : ['text' => 'Hold', 'icon' => 'pause-circle', 'color' => 'amber', 'click' => 'openHoldModal('.$row['id'].')'];
                @endphp
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="eye" href="{{ route('tallstack.jobs.show', [$company, $row['id']]) }}" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Open" />
                    <x-button icon="document-arrow-down" href="{{ route('sales-orders.pdf', $row['id']) }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download PDF" />
                    <x-tallstack.row-actions :items="$extraActions" />
                </div>
            @endinteract

            <x-slot:empty>No jobs found.</x-slot:empty>
        </x-table>
    </x-card>

    {{-- Hold — pauses the auto-close-operationally trigger on this one job;
         a reason is required, matching this app's "exceptional actions
         require reason and audit data" convention (e.g.
         App\Actions\Procurement\ApproveVendorPoVariance). --}}
    <x-modal wire="showHoldModal" title="Hold this job" center="sm">
        <div class="flex flex-col gap-4">
            <p class="text-sm text-gray-500 dark:text-gray-400!">Pauses automatic operational closure on this job until released. Only Owner/Admin may place or release a hold.</p>
            <x-textarea wire:model="holdReason" label="Reason" required placeholder="e.g. Under dispute, pending a revision to the delivery scope" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showHoldModal', false)" />
            <x-button text="Hold" color="amber" wire:click="placeHold" loading="placeHold" spinner="dots" />
        </x-slot:footer>
    </x-modal>
</div>
