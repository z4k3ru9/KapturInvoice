<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Recurring Invoices']]" title="Recurring Invoices">
        <x-slot:actions>
            {{-- color="blue", not "primary" — see the Quotations register's
                 own "+New" button for why: a general action shouldn't
                 borrow the tenant's brand color. --}}
            <x-button text="New recurring invoice" icon="plus" color="blue" sm class="h-9" href="{{ route('tallstack.recurring-invoices.create', $company) }}" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Stat row — same "compact" x-stats scope as Quotations/Dashboard.
         "Auto-bill enabled" and "Invoices generated" are counted from real
         Invoice rows (the `auto_bill` column and the `generatedInvoices()`
         relation), never a new stored aggregate. --}}
    <div class="grid grid-cols-2 min-[820px]:!grid-cols-4 gap-2.5">
        <x-stats scope="compact" title="Active schedules" icon="arrow-path" color="green">
            <span class="text-lg font-bold tabular-nums">{{ $stats['active'] }}</span>
            <x-slot:footer>No end date, or not yet reached</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Ended" icon="stop-circle" color="gray">
            <span class="text-lg font-bold tabular-nums">{{ $stats['ended'] }}</span>
            <x-slot:footer>Past their end date</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Auto-bill enabled" icon="bolt" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['autoBill'] }}</span>
            <x-slot:footer>Of all schedules</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Invoices generated" icon="document-duplicate" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['totalGenerated'] }}</span>
            <x-slot:footer>All time, across every schedule</x-slot:footer>
        </x-stats>
    </div>

    {{-- No enforced pause/resume mechanism exists on the backend — see
         App\Livewire\TallStackRecurringInvoices's docblock. This helper
         line keeps the screen honest about "Generate now" always being a
         manual, on-demand action rather than implying a running
         scheduler. --}}
    <p class="text-xs text-gray-400 -mt-2">Recurring invoices are generated manually with "Generate now" below — there is no automatic background scheduler in this build yet.</p>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Schedules</span>
                <div class="w-full sm:w-64">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search number or client…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'frequency_label', 'label' => 'Frequency'],
            ['index' => 'next_date', 'label' => 'Next invoice date'],
            ['index' => 'amount', 'label' => 'Amount', 'align' => 'right'],
            ['index' => 'status_label', 'label' => 'Status'],
            ['index' => 'generated_count', 'label' => 'Generated'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$templates" paginate loading>
            @interact('column_client', $row)
                <div class="flex flex-col">
                    <span class="font-medium text-gray-800 dark:text-gray-100">{{ $row['client'] }}</span>
                    <span class="font-mono text-[11px] text-gray-400" title="{{ $row['number'] }}">{{ \App\Support\TallStack\DocumentNumber::short($row['number']) }}</span>
                </div>
            @endinteract

            @interact('column_frequency_label', $row)
                <x-badge text="{{ $row['frequency_label'] }}" :color="$row['frequency_color']" sm />
            @endinteract

            @interact('column_status_label', $row)
                <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm />
            @endinteract

            @interact('column_generated_count', $row)
                <span class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">{{ $row['generated_count'] }} invoice{{ $row['generated_count'] === 1 ? '' : 's' }}</span>
            @endinteract

            @interact('column_actions', $row, $company)
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="pencil-square" href="{{ route('tallstack.recurring-invoices.edit', [$company, $row['id']]) }}" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Edit" />
                    <x-dropdown icon="ellipsis-vertical" scope="row-action">
                        <x-dropdown.items text="Generate now" icon="bolt" wire:click="generateNow({{ $row['id'] }})" wire:confirm="Generate a new invoice from this schedule now?" />
                        <x-dropdown.items text="View generated invoices" icon="document-duplicate" href="{{ route('tallstack.recurring-invoices.edit', [$company, $row['id']]) }}#generated-invoices" />
                    </x-dropdown>
                </div>
            @endinteract

            <x-slot:empty>No recurring invoices set up — New recurring invoice to get started.</x-slot:empty>
        </x-table>
    </x-card>
</div>
