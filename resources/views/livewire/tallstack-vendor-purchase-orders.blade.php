<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Vendor purchase orders', 'icon' => 'shopping-cart']]" title="Vendor Purchase Orders">
        <x-slot:actions>
            <x-button text="New PO" icon="plus" color="blue" sm class="h-9" href="{{ route('tallstack.vendor-purchase-orders.create', $company) }}" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Two stacked rows: the one Rupiah (currency) card gets its own row
         so a large approved-value total never fights the two plain count
         cards for space, matching the Dashboard's own stat-row treatment.
         No outer wire:loading grid to escape here (this page's stats are
         computed synchronously in render()), so both rows are plain
         stacked grids inside a flex column. --}}
    <div class="flex flex-col gap-2.5">
        <div class="grid grid-cols-1 gap-2.5">
            <x-stats scope="compact" title="Value" icon="banknotes" color="blue">
                <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['totalValue'] }}</span>
            </x-stats>
        </div>
        <div class="grid grid-cols-2 gap-2.5">
            <x-stats scope="compact" title="Draft" icon="pencil-square" color="gray">
                <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['draft'] }}</span>
            </x-stats>

            <x-stats scope="compact" title="Approved" icon="check-circle" color="green">
                <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['approved'] }}</span>
            </x-stats>
        </div>
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

                <div class="flex items-center gap-2 w-full sm:flex-1 sm:min-w-[240px]">
                    <div class="flex-1 min-w-0">
                        <x-input wire:model.live.debounce.400ms="search" placeholder="Search number or vendor…" icon="magnifying-glass" clearable />
                    </div>
                    <x-tallstack.quantity-select />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'number', 'label' => 'Number'],
            ['index' => 'vendor', 'label' => 'Vendor'],
            ['index' => 'po_date', 'label' => 'Date'],
            ['index' => 'total', 'label' => 'Total', 'align' => 'right'],
            ['index' => 'payment_ceiling', 'label' => 'Payment ceiling', 'align' => 'right', 'sortable' => false],
            ['index' => 'status', 'label' => 'Status'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$purchaseOrders" :sort="$sort" striped paginate loading>
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
                        ['text' => 'Open', 'icon' => 'eye', 'href' => route('tallstack.vendor-purchase-orders.edit', [$company, $row['id']])],
                        ['text' => 'Download PDF', 'icon' => 'document-arrow-down', 'href' => route('vendor-purchase-orders.pdf', $row['id']), 'target' => '_blank'],
                    ];
                    // Only ever populated for a Draft row — Force delete's
                    // full predicate (no bills) is still re-checked
                    // server-side by App\Actions\Procurement\ForceDeleteVendorPurchaseOrder.
                    $extraActions = $row['status'] === \App\Enums\VendorPurchaseOrderStatus::Draft ? [
                        ['text' => 'Approve', 'icon' => 'check-circle', 'color' => 'green', 'click' => 'approve('.$row['id'].')', 'confirm' => 'Approve this vendor purchase order?'],
                        ['text' => 'Force delete', 'icon' => 'trash', 'color' => 'red', 'click' => 'forceDelete('.$row['id'].')', 'confirm' => 'Permanently delete this vendor purchase order? This cannot be undone.'],
                    ] : [];
                @endphp
                <x-tallstack.row-actions :primary="$primaryActions" :items="$extraActions" />
            @endinteract

            <x-slot:empty>No vendor purchase orders found.</x-slot:empty>
        </x-table>
    </x-card>
</div>
