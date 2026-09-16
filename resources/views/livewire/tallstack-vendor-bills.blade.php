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
                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" wire:click="filterStatus(null)"
                            class="px-2.5 py-1 rounded-md text-xs font-semibold {{ $status === null ? 'bg-[color:var(--ts-primary)] text-white' : 'bg-gray-100 dark:bg-gray-800! text-gray-600 dark:text-gray-300!' }}">
                        All
                    </button>
                    @foreach ($statuses as $case)
                        <button type="button" wire:click="filterStatus('{{ $case->value }}')"
                                class="px-2.5 py-1 rounded-md text-xs font-semibold {{ $status === $case->value ? 'bg-[color:var(--ts-primary)] text-white' : 'bg-gray-100 dark:bg-gray-800! text-gray-600 dark:text-gray-300!' }}">
                            {{ $case->getLabel() }}
                        </button>
                    @endforeach
                </div>

                <div class="w-full sm:w-64">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search number or vendor…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'number', 'label' => 'Number'],
            ['index' => 'vendor', 'label' => 'Vendor'],
            ['index' => 'po_number', 'label' => 'Vendor PO'],
            ['index' => 'bill_date', 'label' => 'Date'],
            ['index' => 'total', 'label' => 'Total', 'align' => 'right'],
            ['index' => 'balance', 'label' => 'Balance', 'align' => 'right'],
            ['index' => 'status', 'label' => 'Status'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$bills" paginate loading>
            @interact('column_number', $row)
                <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200!" title="{{ $row['number'] }}">
                    {{ \App\Support\TallStack\DocumentNumber::short($row['number']) }}
                </span>
            @endinteract

            @interact('column_status', $row)
                <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm light />
            @endinteract

            @interact('column_actions', $row, $company)
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="eye" href="{{ route('tallstack.vendor-bills.edit', [$company, $row['id']]) }}" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Open" />
                    <x-button icon="document-arrow-down" href="{{ route('vendor-bills.pdf', $row['id']) }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download PDF" />
                    <x-dropdown icon="ellipsis-vertical" scope="row-action">
                        @if ($row['status'] === \App\Enums\VendorBillStatus::Draft)
                            <x-dropdown.items text="Submit" icon="paper-airplane" wire:click="submit({{ $row['id'] }})" />
                        @endif
                        @if ($row['status'] === \App\Enums\VendorBillStatus::Submitted)
                            <x-dropdown.items text="Approve" icon="check-circle" wire:click="approve({{ $row['id'] }})" />
                        @endif
                        {{-- Only ever shown for a Draft row — the guard's
                             full predicate (no payments) is still
                             re-checked server-side by
                             App\Actions\Procurement\ForceDeleteVendorBill. --}}
                        @if ($row['status'] === \App\Enums\VendorBillStatus::Draft)
                            <x-dropdown.items text="Force delete" icon="trash" wire:click="forceDelete({{ $row['id'] }})" wire:confirm="Permanently delete this vendor bill? This cannot be undone." />
                        @endif
                    </x-dropdown>
                </div>
            @endinteract

            <x-slot:empty>No vendor bills found.</x-slot:empty>
        </x-table>
    </x-card>
</div>
