<div class="max-w-[1400px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Quotes']]" title="Quotes" />

    {{-- Stat row — same "compact" x-stats scope as Invoices/Quotations. --}}
    <div class="grid grid-cols-1 min-[820px]:!grid-cols-3 gap-2.5">
        <x-stats scope="compact" title="Total" icon="document-duplicate" color="gray">
            <span class="text-lg font-bold tabular-nums">{{ $stats['total'] }}</span>
            <x-slot:footer>Legacy/imported quotes</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Converted" icon="arrow-right-circle" color="green">
            <span class="text-lg font-bold tabular-nums">{{ $stats['converted'] }}</span>
            <x-slot:footer>Already turned into an invoice</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Not yet converted" icon="document-text" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['notConverted'] }}</span>
            <x-slot:footer>Still just a quote</x-slot:footer>
        </x-stats>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Legacy quotes imported from the old system. To create a new quote, use
                    <a class="font-semibold text-blue-600 hover:underline" href="{{ route('tallstack.quotations', $company) }}">Quotations</a> instead.
                </span>

                <div class="w-full sm:w-64">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search number or client…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'number', 'label' => 'Number'],
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'invoice_date', 'label' => 'Quote date'],
            ['index' => 'total', 'label' => 'Total', 'align' => 'right'],
            ['index' => 'status', 'label' => 'Status'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$quotes" paginate loading>
            {{--
                Dense overview list: just the generated sequence (last 4
                digits) — see App\Support\TallStack\DocumentNumber's own
                docblock (same pattern as Invoices/Quotations).
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
                    <x-button icon="eye" href="{{ route('tallstack.quotes.edit', [$company, $row['id']]) }}" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Open" />
                    <x-button icon="document-arrow-down" href="{{ route('invoices.pdf', $row['id']) }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download PDF" />
                    <x-dropdown icon="ellipsis-vertical" scope="row-action">
                        <x-dropdown.items text="{{ $row['status'] === \App\Enums\InvoiceStatus::Draft ? 'Send' : 'Resend' }}" icon="paper-airplane" wire:click="send({{ $row['id'] }})" />
                        <x-dropdown.items text="Convert to invoice" icon="arrow-right-circle" wire:click="convertToInvoice({{ $row['id'] }})" wire:confirm="Convert this quote to a real invoice?" />
                        {{-- Only ever shown for a Draft row — the guard's
                             full predicate (no payments/allocations/
                             corrections/converted invoices) is still
                             re-checked server-side by
                             App\Actions\Billing\ForceDeleteQuote. --}}
                        @if ($row['status'] === \App\Enums\InvoiceStatus::Draft)
                            <x-dropdown.items text="Force delete" icon="trash" wire:click="forceDelete({{ $row['id'] }})" wire:confirm="Permanently delete this quote? This cannot be undone." />
                        @endif
                    </x-dropdown>
                </div>
            @endinteract

            <x-slot:empty>No quotes found.</x-slot:empty>
        </x-table>
    </x-card>
</div>
