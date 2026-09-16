<div class="max-w-[1400px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Quotes', 'icon' => 'document-duplicate']]" title="Quotes" />

    {{-- Stat row — same "compact" x-stats scope as Invoices/Quotations. Title + number only, no footer (see memory.md's stat-card density pass). --}}
    <div class="grid grid-cols-1 min-[820px]:!grid-cols-3 gap-2.5">
        <x-stats scope="compact" title="Total" icon="document-duplicate" color="gray">
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['total'] }}</span>
        </x-stats>

        <x-stats scope="compact" title="Converted" icon="arrow-right-circle" color="green">
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['converted'] }}</span>
        </x-stats>

        <x-stats scope="compact" title="Unconverted" icon="document-text" color="blue">
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['notConverted'] }}</span>
        </x-stats>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <span class="text-sm text-gray-500 dark:text-gray-400!">
                    Legacy quotes imported from the old system. To create a new quote, use
                    <a class="font-semibold text-blue-600 dark:text-blue-400! hover:underline" href="{{ route('tallstack.quotations', $company) }}">Quotations</a> instead.
                </span>

                <div class="flex items-center gap-2 w-full sm:flex-1 sm:min-w-[240px]">
                    <div class="flex-1 min-w-0">
                        <x-input wire:model.live.debounce.400ms="search" placeholder="Search number or client…" icon="magnifying-glass" clearable />
                    </div>
                    <x-tallstack.quantity-select />
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
        ]" :rows="$quotes" :sort="$sort" striped paginate loading>
            {{--
                Dense overview list: just the generated sequence (last 4
                digits) — see App\Support\TallStack\DocumentNumber's own
                docblock (same pattern as Invoices/Quotations).
                title="" gives the full number as a native hover tooltip.
            --}}
            @interact('column_number', $row)
                <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200!" title="{{ $row['number'] }}">
                    {{ \App\Support\TallStack\DocumentNumber::short($row['number']) }}
                </span>
            @endinteract

            @interact('column_status', $row)
                <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm light />
            @endinteract

            @interact('column_actions', $row, $company)
                @php
                    $primaryActions = [
                        ['text' => 'Open', 'icon' => 'eye', 'href' => route('tallstack.quotes.edit', [$company, $row['id']])],
                        ['text' => 'Download PDF', 'icon' => 'document-arrow-down', 'href' => route('invoices.pdf', $row['id']), 'target' => '_blank'],
                    ];
                    $extraActions = [];
                    $extraActions[] = ['text' => $row['status'] === \App\Enums\InvoiceStatus::Draft ? 'Send' : 'Resend', 'icon' => 'paper-airplane', 'click' => 'send('.$row['id'].')'];
                    $extraActions[] = ['text' => 'Convert to invoice', 'icon' => 'arrow-right-circle', 'click' => 'convertToInvoice('.$row['id'].')', 'confirm' => 'Convert this quote to a real invoice?'];
                    // Only ever shown for a Draft row — the guard's full
                    // predicate (no payments/allocations/corrections/
                    // converted invoices) is still re-checked server-side by
                    // App\Actions\Billing\ForceDeleteQuote.
                    if ($row['status'] === \App\Enums\InvoiceStatus::Draft) {
                        $extraActions[] = ['text' => 'Force delete', 'icon' => 'trash', 'color' => 'red', 'click' => 'forceDelete('.$row['id'].')', 'confirm' => 'Permanently delete this quote? This cannot be undone.'];
                    }
                @endphp
                <x-tallstack.row-actions :primary="$primaryActions" :items="$extraActions" />
            @endinteract

            <x-slot:empty>No quotes found.</x-slot:empty>
        </x-table>
    </x-card>
</div>
