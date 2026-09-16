<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Vendor purchase orders']]" title="Vendor Purchase Orders">
        <x-slot:actions>
            <x-button text="New PO" icon="plus" color="blue" sm class="h-9" href="{{ route('tallstack.vendor-purchase-orders.create', $company) }}" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <div class="grid grid-cols-2 min-[820px]:!grid-cols-3 gap-2.5">
        <x-stats scope="compact" title="Draft POs" icon="pencil-square" color="gray">
            <span class="text-lg font-bold tabular-nums">{{ $stats['draft'] }}</span>
            <x-slot:footer>Not yet approved</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Approved POs" icon="check-circle" color="green">
            <span class="text-lg font-bold tabular-nums">{{ $stats['approved'] }}</span>
            <x-slot:footer>Active payment ceilings</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Approved value" icon="banknotes" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['totalValue'] }}</span>
            <x-slot:footer>Sum of approved POs</x-slot:footer>
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
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search number or vendor…" icon="magnifying-glass" clearable />
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
        ]" :rows="$purchaseOrders" :sort="$sort" :filter="['quantity' => 'quantity']" striped paginate loading>
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
                    // Only ever populated for a Draft row — Force delete's
                    // full predicate (no bills) is still re-checked
                    // server-side by App\Actions\Procurement\ForceDeleteVendorPurchaseOrder.
                    $extraActions = $row['status'] === \App\Enums\VendorPurchaseOrderStatus::Draft ? [
                        ['text' => 'Approve', 'icon' => 'check-circle', 'color' => 'green', 'click' => 'approve('.$row['id'].')', 'confirm' => 'Approve this vendor purchase order?'],
                        ['text' => 'Force delete', 'icon' => 'trash', 'color' => 'red', 'click' => 'forceDelete('.$row['id'].')', 'confirm' => 'Permanently delete this vendor purchase order? This cannot be undone.'],
                    ] : [];
                @endphp
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="eye" href="{{ route('tallstack.vendor-purchase-orders.edit', [$company, $row['id']]) }}" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Open" />
                    <x-button icon="document-arrow-down" href="{{ route('vendor-purchase-orders.pdf', $row['id']) }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download PDF" />
                    <x-tallstack.row-actions :items="$extraActions" />
                </div>
            @endinteract

            <x-slot:empty>No vendor purchase orders found.</x-slot:empty>
        </x-table>
    </x-card>
</div>
