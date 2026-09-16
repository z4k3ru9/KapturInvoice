<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Delivery Orders', 'icon' => 'truck']]" title="Delivery Orders" />

    {{-- Stat row — same "compact" x-stats scope as every other TALL-stack
         register (Quotations/Jobs/Invoices/Payments), computed from real
         DeliveryOrder rows. No status badge column below: a DeliveryOrder
         has no lifecycle of its own — it's only ever written, complete,
         through App\Actions\Delivery\CompleteDelivery — so there is no
         "pending" state to surface here. All-count (no currency), title +
         number only, no footer. --}}
    <div class="grid grid-cols-2 min-[820px]:!grid-cols-4 gap-2.5">
        <x-stats scope="compact" title="Deliveries" icon="truck" color="blue">
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['total'] }}</span>
        </x-stats>

        <x-stats scope="compact" title="This month" icon="calendar-days" color="blue">
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['thisMonth'] }}</span>
        </x-stats>

        <x-stats scope="compact" title="This week" icon="clock" color="amber">
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['thisWeek'] }}</span>
        </x-stats>

        <x-stats scope="compact" title="Jobs" icon="briefcase" color="blue">
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['distinctJobs'] }}</span>
        </x-stats>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">All delivery orders</span>
                <div class="w-full sm:w-64">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search number, job or client…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'number', 'label' => 'Number'],
            ['index' => 'job_number', 'label' => 'Job'],
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'delivery_date', 'label' => 'Date'],
            ['index' => 'items_count', 'label' => 'Lines', 'align' => 'right'],
            ['index' => 'created_by', 'label' => 'Recorded by'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$deliveryOrders" paginate loading>
            @interact('column_number', $row)
                <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200!" title="{{ $row['number'] }}">
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

            @interact('column_actions', $row, $company)
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="eye" href="{{ route('tallstack.delivery-orders.show', [$company, $row['id']]) }}" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="View" />
                    <x-button icon="document-arrow-down" href="{{ route('delivery-orders.pdf', $row['id']) }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download PDF" />
                </div>
            @endinteract

            <x-slot:empty>No delivery orders recorded yet.</x-slot:empty>
        </x-table>
    </x-card>
</div>
