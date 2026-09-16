<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Vendor bills']]" title="Vendor Bills">
        <x-slot:actions>
            <x-button text="New vendor bill" icon="plus" color="blue" sm class="h-9" href="{{ route('tallstack.vendor-bills.create', $company) }}" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <div class="grid grid-cols-2 min-[820px]:!grid-cols-4 gap-2.5">
        <x-stats scope="compact" title="Draft bills" icon="pencil-square" color="gray">
            <span class="text-lg font-bold tabular-nums">{{ $stats['draft'] }}</span>
            <x-slot:footer>Not yet submitted</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Awaiting approval" icon="clock" color="amber">
            <span class="text-lg font-bold tabular-nums">{{ $stats['awaitingApproval'] }}</span>
            <x-slot:footer>Submitted, review needed</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Outstanding balance" icon="banknotes" color="red">
            <span class="text-lg font-bold tabular-nums">{{ $stats['outstandingBalance'] }}</span>
            <x-slot:footer>Approved / partially paid</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Fully paid" icon="check-circle" color="green">
            <span class="text-lg font-bold tabular-nums">{{ $stats['paid'] }}</span>
            <x-slot:footer>All time</x-slot:footer>
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

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <div class="w-full sm:w-64">
                        <x-input wire:model.live.debounce.400ms="search" placeholder="Search number or vendor…" icon="magnifying-glass" clearable />
                    </div>
                    <x-tallstack.quantity-select />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'number', 'label' => 'Number'],
            ['index' => 'vendor', 'label' => 'Vendor'],
            ['index' => 'po_number', 'label' => 'Vendor PO', 'sortable' => false],
            ['index' => 'bill_date', 'label' => 'Date'],
            ['index' => 'total', 'label' => 'Total', 'align' => 'right'],
            ['index' => 'balance', 'label' => 'Balance', 'align' => 'right'],
            ['index' => 'status', 'label' => 'Status'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$bills" :sort="$sort" striped paginate loading>
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
                        ['text' => 'Open', 'icon' => 'eye', 'href' => route('tallstack.vendor-bills.edit', [$company, $row['id']])],
                        ['text' => 'Download PDF', 'icon' => 'document-arrow-down', 'href' => route('vendor-bills.pdf', $row['id']), 'target' => '_blank'],
                    ];
                    $extraActions = [];
                    if ($row['status'] === \App\Enums\VendorBillStatus::Draft) {
                        $extraActions[] = ['text' => 'Submit', 'icon' => 'paper-airplane', 'click' => 'submit('.$row['id'].')'];
                    }
                    if ($row['status'] === \App\Enums\VendorBillStatus::Submitted) {
                        $extraActions[] = ['text' => 'Approve', 'icon' => 'check-circle', 'color' => 'green', 'click' => 'approve('.$row['id'].')'];
                    }
                    // Only ever shown for a Draft row — the guard's full
                    // predicate (no payments) is still re-checked
                    // server-side by App\Actions\Procurement\ForceDeleteVendorBill.
                    if ($row['status'] === \App\Enums\VendorBillStatus::Draft) {
                        $extraActions[] = ['text' => 'Force delete', 'icon' => 'trash', 'color' => 'red', 'click' => 'forceDelete('.$row['id'].')', 'confirm' => 'Permanently delete this vendor bill? This cannot be undone.'];
                    }
                @endphp
                <x-tallstack.row-actions :primary="$primaryActions" :items="$extraActions" />
            @endinteract

            <x-slot:empty>No vendor bills found.</x-slot:empty>
        </x-table>
    </x-card>
</div>
