<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Invoices', 'icon' => 'document-currency-dollar']]" title="Invoices">
        <x-slot:actions>
            {{-- color="brand" — Primary role (AppServiceProvider::
                 registerActionColorPalette()'s docblock): this IS the
                 single main action of the Invoices list, so it gets the
                 tenant's own brand color rather than a fixed hue. --}}
            <x-button text="New invoice" icon="plus" color="brand" sm class="h-9" href="{{ route('tallstack.invoices.create', $company) }}" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Stat row — same "compact" x-stats scope as Quotations/Dashboard.
         Two stacked rows: the two currency (Rupiah) cards get their own
         wider row, the two plain-count cards keep a tight row below — same
         col-span-full pattern as the Dashboard's own stat row; the outer
         wrapper's own classes are unchanged. Title + number only, no footer. --}}
    <div class="grid grid-cols-2 min-[820px]:!grid-cols-4 gap-2.5">
        <div class="col-span-full grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            <x-stats scope="compact" title="Outstanding" icon="banknotes" color="blue">
                <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['outstanding'] }}</span>
            </x-stats>

            <x-stats scope="compact" title="Paid" icon="check-circle" color="green">
                <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['paidThisMonth'] }}</span>
            </x-stats>
        </div>
        <div class="col-span-full grid grid-cols-2 gap-2.5">
            <x-stats scope="compact" title="Overdue" icon="exclamation-triangle" color="red">
                <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['overdueCount'] }}</span>
            </x-stats>

            <x-stats scope="compact" title="Draft" icon="document-text" color="gray">
                <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['draftCount'] }}</span>
            </x-stats>
        </div>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                {{-- Status filter — every real InvoiceStatus case, not an
                     invented merged grouping. A single dropdown, not a pill
                     row: 11 pills wrapped into up to 4 stacked rows at
                     mobile width (confirmed live across every list page
                     that had this pattern), pushing the table below the
                     fold before any data was visible. --}}
                <x-tallstack.filter-dropdown
                    label="{{ $status === null ? 'All statuses' : collect($statuses)->firstWhere('value', $status)?->getLabel() }}"
                    :options="collect([['value' => null, 'label' => 'All statuses']])->concat(collect($statuses)->map(fn ($case) => ['value' => $case->value, 'label' => $case->getLabel()]))"
                    :active="$status"
                    method="filterStatus"
                />

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <div class="w-full sm:w-64">
                        <x-input wire:model.live.debounce.400ms="search" placeholder="Search number or client…" icon="magnifying-glass" clearable />
                    </div>
                    <x-tallstack.quantity-select />
                </div>
            </div>
        </x-slot:header>

        {{-- Sortable: number, client (joined on `clients.name` in
             TallStackInvoices::render()) and invoice_date — the three
             dimensions requested. Every other column is either a money/
             status value not asked for or, for due_date, a second date
             column that would be redundant with invoice_date — all marked
             sortable => false explicitly rather than left to the
             component's own sortable-by-default behavior. --}}
        <x-table :headers="[
            ['index' => 'number', 'label' => 'Number'],
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'invoice_date', 'label' => 'Date'],
            ['index' => 'due_date', 'label' => 'Due', 'sortable' => false],
            ['index' => 'total', 'label' => 'Total', 'align' => 'right', 'sortable' => false],
            ['index' => 'balance', 'label' => 'Balance', 'align' => 'right', 'sortable' => false],
            ['index' => 'status', 'label' => 'Status', 'sortable' => false],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$invoices" :sort="$sort" striped paginate loading>
            {{--
                Dense overview list: just the generated sequence (last 4
                digits) — see App\Support\TallStack\DocumentNumber's own
                docblock (same pattern as Quotations register).
                title="" gives the full number as a native hover tooltip.
            --}}
            @interact('column_number', $row)
                <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200!" title="{{ $row['number'] }}">
                    {{ \App\Support\TallStack\DocumentNumber::short($row['number']) }}
                </span>
            @endinteract

            @interact('column_status', $row)
                <div class="flex items-center gap-1.5">
                    <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm light />
                    {{-- Hold is the override lever for the status-transition
                         automation (invoices:mark-overdue,
                         recurring-invoices:generate-due) — see
                         App\Models\Concerns\Holdable's own docblock. --}}
                    @if ($row['is_held'])
                        <x-icon name="pause-circle" class="w-3.5 h-3.5 text-amber-500" title="On hold: {{ $row['held_reason'] }}" />
                    @endif
                </div>
            @endinteract

            @interact('column_actions', $row, $company)
                @php
                    $primaryActions = [
                        ['text' => 'Open', 'icon' => 'eye', 'href' => route('tallstack.invoices.edit', [$company, $row['id']])],
                        ['text' => 'Download PDF', 'icon' => 'document-arrow-down', 'href' => route('invoices.pdf', $row['id']), 'target' => '_blank'],
                    ];
                    $extraActions = [];
                    // Only ever shown for a Draft row — the guard's full
                    // predicate (no payments/allocations/corrections) is
                    // still re-checked server-side by App\Actions\Billing\ForceDeleteInvoice.
                    if ($row['status'] === \App\Enums\InvoiceStatus::Draft) {
                        $extraActions[] = ['text' => 'Force delete', 'icon' => 'trash', 'color' => 'red', 'click' => 'forceDelete('.$row['id'].')', 'confirm' => 'Permanently delete this invoice? This cannot be undone.'];
                    }
                    $extraActions[] = $row['is_held']
                        ? ['text' => 'Release hold', 'icon' => 'play-circle', 'click' => 'releaseHold('.$row['id'].')']
                        : ['text' => 'Hold', 'icon' => 'pause-circle', 'color' => 'amber', 'click' => 'openHoldModal('.$row['id'].')'];
                @endphp
                <x-tallstack.row-actions :primary="$primaryActions" :items="$extraActions" />
            @endinteract

            <x-slot:empty>No invoices found.</x-slot:empty>
        </x-table>
    </x-card>

    {{-- Hold — pauses invoices:mark-overdue/recurring-invoices:generate-due
         automation on this one invoice; a reason is required, matching
         this app's "exceptional actions require reason and audit data"
         convention (e.g. App\Actions\Procurement\ApproveVendorPoVariance). --}}
    <x-modal wire="showHoldModal" title="Hold this invoice" center="sm">
        <div class="flex flex-col gap-4">
            <p class="text-sm text-gray-500 dark:text-gray-400!">Pauses automatic Overdue marking and recurring auto-generation/issue/send on this invoice until released. Only Owner/Admin may place or release a hold.</p>
            <x-textarea wire:model="holdReason" label="Reason" required placeholder="e.g. Under dispute, pending a revision to the line items" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showHoldModal', false)" />
            <x-button text="Hold" color="amber" wire:click="placeHold" loading="placeHold" spinner="dots" />
        </x-slot:footer>
    </x-modal>
</div>
