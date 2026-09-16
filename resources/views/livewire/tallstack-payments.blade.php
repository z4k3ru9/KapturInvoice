<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Payments', 'icon' => 'credit-card']]" title="Payments">
        <x-slot:actions>
            {{-- color="brand" — Primary role (AppServiceProvider::
                 registerActionColorPalette()'s docblock): the single main
                 action of the Payments list, so it gets the tenant's own
                 brand color. --}}
            <x-button text="Record payment" icon="plus" color="brand" sm class="h-9" wire:click="openRecordModal" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Stat row — same "compact" x-stats scope as Quotations/Dashboard.
         Two stacked rows: the one Rupiah (currency) card gets its own row
         so a large verified total never fights the three plain count
         cards for space, matching the Dashboard's own stat-row treatment.
         No outer wire:loading grid to escape here (this page's stats are
         computed synchronously in render()), so both rows are plain
         stacked grids inside a flex column. --}}
    <div class="flex flex-col gap-2.5">
        <div class="grid grid-cols-1 gap-2.5">
            <x-stats scope="compact" title="Verified" icon="banknotes" color="green">
                <span class="text-lg font-bold tabular-nums text-gray-900 dark:text-gray-100! break-words">{{ $stats['verifiedTotal'] }}</span>
            </x-stats>
        </div>
        <div class="grid grid-cols-3 gap-2.5">
            <x-stats scope="compact" title="Pending" icon="clock" color="amber">
                <span class="text-lg font-bold tabular-nums text-gray-900 dark:text-gray-100! break-words">{{ $stats['pendingCount'] }}</span>
            </x-stats>

            <x-stats scope="compact" title="Unallocated" icon="arrows-right-left" color="red">
                <span class="text-lg font-bold tabular-nums text-gray-900 dark:text-gray-100! break-words">{{ $stats['unallocatedCount'] }}</span>
            </x-stats>

            <x-stats scope="compact" title="Receipts" icon="document-text" color="blue">
                <span class="text-lg font-bold tabular-nums text-gray-900 dark:text-gray-100! break-words">{{ $stats['receiptsIssued'] }}</span>
            </x-stats>
        </div>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                {{-- Status filter pills — every real PaymentStatus case, not
                     an invented merged grouping, so this never implies a
                     state the enum doesn't have. --}}
                <x-tallstack.filter-dropdown
                    label="{{ $status === null ? 'All statuses' : collect($statuses)->firstWhere('value', $status)?->getLabel() }}"
                    :options="collect([['value' => null, 'label' => 'All statuses']])->concat(collect($statuses)->map(fn ($case) => ['value' => $case->value, 'label' => $case->getLabel()]))"
                    :active="$status"
                    method="filterStatus"
                />

                <div class="flex items-center gap-2 w-full sm:flex-1 sm:min-w-[240px]">
                    <div class="flex-1 min-w-0">
                        <x-input wire:model.live.debounce.400ms="search" placeholder="Search client, invoice, reference…" icon="magnifying-glass" clearable />
                    </div>
                    <x-tallstack.quantity-select />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'invoice', 'label' => 'Invoice', 'sortable' => false],
            ['index' => 'amount', 'label' => 'Amount', 'align' => 'right'],
            ['index' => 'method', 'label' => 'Method'],
            ['index' => 'status', 'label' => 'Status'],
            ['index' => 'receipt_number', 'label' => 'Receipt', 'sortable' => false],
            ['index' => 'payment_date', 'label' => 'Date'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$payments" :sort="$sort" striped paginate loading>
            @interact('column_status', $row)
                <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm light />
            @endinteract

            @interact('column_receipt_number', $row)
                <span class="font-mono text-xs text-gray-600 dark:text-gray-300!">{{ $row['receipt_number'] ?? '—' }}</span>
            @endinteract

            @interact('column_actions', $row, $company)
                @php
                    $primaryActions = [
                        ['text' => 'Allocate / view', 'icon' => 'arrows-right-left', 'href' => route('tallstack.payments.allocate', [$company, $row['id']])],
                    ];
                    if ($row['has_receipt']) {
                        $primaryActions[] = ['text' => 'Download receipt', 'icon' => 'document-arrow-down', 'href' => route('receipts.pdf', $row['receipt_id']), 'target' => '_blank'];
                    }
                    $extraActions = [];
                    if ($row['status'] === \App\Enums\PaymentStatus::Pending) {
                        $extraActions[] = ['text' => 'Verify', 'icon' => 'check-circle', 'color' => 'green', 'click' => 'openVerifyModal('.$row['id'].')'];
                    }
                    if ($row['status'] === \App\Enums\PaymentStatus::Verified && ! $row['has_receipt']) {
                        $extraActions[] = ['text' => 'Issue receipt', 'icon' => 'document-text', 'click' => 'issueReceipt('.$row['id'].')', 'confirm' => 'Issue a receipt for this payment?'];
                    }
                    if ($row['has_receipt']) {
                        $extraActions[] = ['text' => 'Send receipt', 'icon' => 'envelope', 'click' => 'sendReceipt('.$row['id'].')', 'confirm' => "Email this receipt to the client's billing contact?"];
                    }
                    if (in_array($row['status'], [\App\Enums\PaymentStatus::Pending, \App\Enums\PaymentStatus::Verified], true)) {
                        $extraActions[] = ['text' => 'Reverse', 'icon' => 'no-symbol', 'color' => 'red', 'click' => 'openReverseModal('.$row['id'].')'];
                    }
                    // Only ever shown for a Pending row — the guard's full
                    // predicate (no receipt/allocations/verification
                    // history) is still re-checked server-side by
                    // App\Actions\Receivables\ForceDeletePayment.
                    if ($row['status'] === \App\Enums\PaymentStatus::Pending) {
                        $extraActions[] = ['text' => 'Force delete', 'icon' => 'trash', 'color' => 'red', 'click' => 'forceDelete('.$row['id'].')', 'confirm' => 'Permanently delete this payment? This cannot be undone.'];
                    }
                @endphp
                <x-tallstack.row-actions :primary="$primaryActions" :items="$extraActions" />
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
            <p class="text-sm text-gray-500 dark:text-gray-400!">Only Accountant, Admin, or Owner may verify a payment.</p>
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
