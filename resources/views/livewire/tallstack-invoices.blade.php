<div class="max-w-[1400px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Invoices']]" title="Invoices">
        <x-slot:actions>
            {{-- color="brand" — Primary role (AppServiceProvider::
                 registerActionColorPalette()'s docblock): this IS the
                 single main action of the Invoices list, so it gets the
                 tenant's own brand color rather than a fixed hue. --}}
            <x-button text="New invoice" icon="plus" color="brand" sm class="h-9" href="{{ route('tallstack.invoices.create', $company) }}" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Stat row — same "compact" x-stats scope as Quotations/Dashboard. --}}
    <div class="grid grid-cols-2 min-[820px]:!grid-cols-4 gap-2.5">
        <x-stats scope="compact" title="Outstanding balance" icon="banknotes" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['outstanding'] }}</span>
            <x-slot:footer>Issued, partial or overdue</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Overdue" icon="exclamation-triangle" color="red">
            <span class="text-lg font-bold tabular-nums">{{ $stats['overdueCount'] }}</span>
            <x-slot:footer>Invoices past due date</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="In draft" icon="document-text" color="gray">
            <span class="text-lg font-bold tabular-nums">{{ $stats['draftCount'] }}</span>
            <x-slot:footer>Draft or approved</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Paid this month" icon="check-circle" color="green">
            <span class="text-lg font-bold tabular-nums">{{ $stats['paidThisMonth'] }}</span>
            <x-slot:footer>Marked paid since the 1st</x-slot:footer>
        </x-stats>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                {{-- Status filter pills — every real InvoiceStatus case, not
                     an invented merged grouping. --}}
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
            ['index' => 'invoice_date', 'label' => 'Date'],
            ['index' => 'due_date', 'label' => 'Due'],
            ['index' => 'total', 'label' => 'Total', 'align' => 'right'],
            ['index' => 'balance', 'label' => 'Balance', 'align' => 'right'],
            ['index' => 'status', 'label' => 'Status'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$invoices" paginate loading>
            {{--
                Dense overview list: just the generated sequence (last 4
                digits) — see App\Support\TallStack\DocumentNumber's own
                docblock (same pattern as Quotations register).
                title="" gives the full number as a native hover tooltip.
            --}}
            @interact('column_number', $row)
                <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200" title="{{ $row['number'] }}">
                    {{ \App\Support\TallStack\DocumentNumber::short($row['number']) }}
                </span>
            @endinteract

            @interact('column_status', $row)
                <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm />
            @endinteract

            @interact('column_actions', $row, $company)
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="eye" href="{{ route('tallstack.invoices.edit', [$company, $row['id']]) }}" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Open" />
                    <x-button icon="document-arrow-down" href="{{ route('invoices.pdf', $row['id']) }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download PDF" />
                </div>
            @endinteract

            <x-slot:empty>No invoices found.</x-slot:empty>
        </x-table>
    </x-card>
</div>
