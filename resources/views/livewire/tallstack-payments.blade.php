<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Payments']]" title="Payments">
        <x-slot:actions>
            {{-- color="brand" — Primary role (AppServiceProvider::
                 registerActionColorPalette()'s docblock): the single main
                 action of the Payments list, so it gets the tenant's own
                 brand color. --}}
            <x-button text="Record payment" icon="plus" color="brand" sm class="h-9" wire:click="openRecordModal" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Stat row — same "compact" x-stats scope as Quotations/Dashboard. --}}
    <div class="grid grid-cols-2 min-[820px]:!grid-cols-4 gap-2.5">
        <x-stats scope="compact" title="Pending verification" icon="clock" color="amber">
            <span class="text-lg font-bold tabular-nums">{{ $stats['pendingCount'] }}</span>
            <x-slot:footer>Awaiting Verify</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Verified total" icon="banknotes" color="green">
            <span class="text-lg font-bold tabular-nums">{{ $stats['verifiedTotal'] }}</span>
            <x-slot:footer>Sum of verified payments</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Unallocated" icon="arrows-right-left" color="red">
            <span class="text-lg font-bold tabular-nums">{{ $stats['unallocatedCount'] }}</span>
            <x-slot:footer>Verified, not yet allocated</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Receipts issued" icon="document-text" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['receiptsIssued'] }}</span>
            <x-slot:footer>Total to date</x-slot:footer>
        </x-stats>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                {{-- Status filter pills — every real PaymentStatus case, not
                     an invented merged grouping, so this never implies a
                     state the enum doesn't have. --}}
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
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search client, invoice, reference…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'invoice', 'label' => 'Invoice'],
            ['index' => 'amount', 'label' => 'Amount', 'align' => 'right'],
            ['index' => 'method', 'label' => 'Method'],
            ['index' => 'status', 'label' => 'Status'],
            ['index' => 'receipt_number', 'label' => 'Receipt'],
            ['index' => 'payment_date', 'label' => 'Date'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$payments" paginate loading>
            @interact('column_status', $row)
                <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm light />
            @endinteract

            @interact('column_receipt_number', $row)
                <span class="font-mono text-xs text-gray-600 dark:text-gray-300">{{ $row['receipt_number'] ?? '—' }}</span>
            @endinteract

            @interact('column_actions', $row, $company)
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="arrows-right-left" href="{{ route('tallstack.payments.allocate', [$company, $row['id']]) }}" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Allocate / view" />
                    @if ($row['has_receipt'])
                        <x-button icon="document-arrow-down" href="{{ route('receipts.pdf', $row['receipt_id']) }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download receipt" />
                    @endif
                    <x-dropdown icon="ellipsis-vertical" scope="row-action">
                        @if ($row['status'] === \App\Enums\PaymentStatus::Pending)
                            <x-dropdown.items text="Verify" icon="check-circle" wire:click="openVerifyModal({{ $row['id'] }})" />
                        @endif
                        @if ($row['status'] === \App\Enums\PaymentStatus::Verified && ! $row['has_receipt'])
                            <x-dropdown.items text="Issue receipt" icon="document-text" wire:click="issueReceipt({{ $row['id'] }})" wire:confirm="Issue a receipt for this payment?" />
                        @endif
                        @if (in_array($row['status'], [\App\Enums\PaymentStatus::Pending, \App\Enums\PaymentStatus::Verified], true))
                            <x-dropdown.items text="Reverse" icon="no-symbol" wire:click="openReverseModal({{ $row['id'] }})" />
                        @endif
                        {{-- Only ever shown for a Pending row — the guard's
                             full predicate (no receipt/allocations/
                             verification history) is still re-checked
                             server-side by
                             App\Actions\Receivables\ForceDeletePayment. --}}
                        @if ($row['status'] === \App\Enums\PaymentStatus::Pending)
                            <x-dropdown.items text="Force delete" icon="trash" wire:click="forceDelete({{ $row['id'] }})" wire:confirm="Permanently delete this payment? This cannot be undone." />
                        @endif
                    </x-dropdown>
                </div>
            @endinteract

            <x-slot:empty>No payments found.</x-slot:empty>
        </x-table>
    </x-card>

    {{-- Record payment — RecordCustomerPayment always starts Pending and
         requires a proof upload; status is never set here directly. --}}
    <x-modal wire="showRecordModal" title="Record payment" center="sm" scrollable>
        <div class="flex flex-col gap-4">
            <x-select.styled wire:model="record_client_id" label="Client" searchable required
                :options="$clients->map(fn ($c) => ['label' => $c->name, 'value' => (string) $c->id])->all()" />
            <x-select.styled wire:model="record_method" label="Method" required
                :options="collect($paymentMethods)->map(fn ($m) => ['label' => $m->getLabel(), 'value' => $m->value])->all()" />
            <x-input wire:model="record_amount" label="Amount" type="number" step="0.01" required />
            <x-date wire:model="record_payment_date" label="Payment date" required />
            <x-input wire:model="record_reference" label="Transaction reference" />
            <x-upload wire:model="record_proof" label="Proof of payment" hint="Required before this payment can be verified — PDF/JPG/PNG." />
            <x-textarea wire:model="record_notes" label="Notes" rows="3" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showRecordModal', false)" />
            {{-- color="brand" — this modal's own single commit action (Primary role). --}}
            <x-button text="Record payment" color="brand" wire:click="recordPayment" loading="recordPayment" spinner="dots" />
        </x-slot:footer>
    </x-modal>

    {{-- Verify — a cheque requires a cleared date before it can verify,
         same guard as App\Actions\Receivables\VerifyCustomerPayment. --}}
    <x-modal wire="showVerifyModal" title="Verify payment" center="sm">
        <div class="flex flex-col gap-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Only Accountant, Admin, or Owner may verify a payment.</p>
            <x-date wire:model="verify_cheque_cleared_at" label="Cheque cleared on" hint="Only required for a cheque payment that hasn't cleared yet." />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showVerifyModal', false)" />
            <x-button text="Verify" color="green" wire:click="verify" loading="verify" spinner="dots" />
        </x-slot:footer>
    </x-modal>

    {{-- Reverse — preserves the payment/receipt/allocation history, never deletes it. --}}
    <x-modal wire="showReverseModal" title="Reverse payment" center="sm">
        <div class="flex flex-col gap-4">
            <x-textarea wire:model="reverse_reason" label="Reason" rows="3" required />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showReverseModal', false)" />
            <x-button text="Reverse" color="red" wire:click="reverse" loading="reverse" spinner="dots" />
        </x-slot:footer>
    </x-modal>
</div>
