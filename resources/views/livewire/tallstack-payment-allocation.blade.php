<div class="max-w-[1200px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header
        :crumbs="[
            ['label' => $company->name, 'url' => route('tallstack.payments', $company)],
            ['label' => 'Payments', 'url' => route('tallstack.payments', $company)],
            ['label' => $payment->reference ?: ('#'.$payment->id)],
        ]"
        :title="$payment->reference ?: ('Payment #'.$payment->id)"
    >
        <x-slot:badge>
            <x-badge text="{{ $payment->status->getLabel() }}" :color="$statusColor" sm />
        </x-slot:badge>
        <x-slot:actions>
            @if ($payment->receipt)
                <x-button icon="document-arrow-down" text="Download receipt" href="{{ route('receipts.pdf', $payment->receipt) }}" target="_blank" color="gray" sm class="h-9" />
            @endif
        </x-slot:actions>
    </x-tallstack.page-header>

    {{--
        Status action bar — every transition here calls the exact same
        App\Actions\Receivables\* class the Filament table row actions use;
        no status is ever set directly from this UI. Same standardized
        semantic palette as the Quotation detail page's status bar: green
        for a forward/positive move, blue for allocate/amend (neutral
        working action), red for reverse — never the tenant's own brand
        "primary" color.
    --}}
    <div class="flex flex-wrap items-center gap-2">
        @if ($payment->status === \App\Enums\PaymentStatus::Pending)
            <x-button text="Verify" icon="check-circle" color="green" sm wire:click="openVerifyModal" />
        @endif
        @if (! $payment->receipt)
            <x-button text="Allocate" icon="arrows-right-left" color="blue" sm wire:click="openAllocationModal" />
        @else
            <x-button text="Amend allocation" icon="document-duplicate" color="blue" sm wire:click="openAllocationModal" />
        @endif
        @if ($payment->status === \App\Enums\PaymentStatus::Verified && ! $payment->receipt)
            <x-button text="Issue receipt" icon="document-text" color="green" sm wire:click="issueReceipt" wire:confirm="Issue a receipt for this payment?" loading="issueReceipt" spinner="dots" />
        @endif
        @if (in_array($payment->status, [\App\Enums\PaymentStatus::Pending, \App\Enums\PaymentStatus::Verified], true))
            <x-button text="Reverse" icon="no-symbol" color="red" sm wire:click="openReverseModal" />
        @endif
    </div>

    {{-- Incoming payment / allocated total / remaining — mirrors the top
         summary cards in the Stitch "Allocation Panel" mockup, using only
         real payment/allocation figures (the mockup's own "Live
         escrow/credit ledger" affordance has no domain equivalent, so
         it's not reproduced here — see this component's docblock). --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
        <x-stats scope="compact" title="Incoming payment" icon="banknotes" color="blue">
            <span class="text-lg font-bold tabular-nums text-gray-900 dark:text-gray-100!">{{ $incomingAmount }}</span>
            <x-slot:footer>{{ $payment->client?->name ?? '—' }} &middot; {{ $payment->payment_date?->format('d M Y') ?? '—' }}</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Allocated total" icon="arrows-right-left" color="green">
            <span class="text-lg font-bold tabular-nums text-gray-900 dark:text-gray-100!">{{ $allocatedTotal }}</span>
            <x-slot:footer>Across {{ $payment->allocations->count() }} invoice(s)</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="{{ $remainingIsOverpaid ? 'Overpayment' : 'Remaining' }}" icon="exclamation-triangle" :color="$remainingIsOverpaid ? 'red' : 'gray'">
            <span class="text-lg font-bold tabular-nums text-gray-900 dark:text-gray-100!">{{ $remaining }}</span>
            <x-slot:footer>Unallocated balance</x-slot:footer>
        </x-stats>
    </div>

    <div class="grid lg:grid-cols-[1.4fr_1fr] gap-4 items-start">
        <x-card>
            <x-slot:header>
                <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Current allocations</span>
            </x-slot:header>

            <x-table :headers="[
                ['index' => 'invoice', 'label' => 'Invoice', 'sortable' => false],
                ['index' => 'amount', 'label' => 'Allocated', 'sortable' => false, 'align' => 'right'],
            ]" :rows="$payment->allocations->map(fn ($a) => ['invoice' => $a->invoice?->number ?? '—', 'amount' => \App\Support\Dashboard\Money::format((float) $a->amount, $currency)])">
                <x-slot:empty>No invoices allocated yet.</x-slot:empty>
            </x-table>
        </x-card>

        <x-card>
            <x-slot:header>
                <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Payment details</span>
            </x-slot:header>
            <div class="flex flex-col gap-2 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400!">Method</span>
                    <span class="font-semibold">{{ $payment->method ? str_replace('_', ' ', ucfirst($payment->method)) : '—' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400!">Reference</span>
                    <span>{{ $payment->reference ?? '—' }}</span>
                </div>
                @if ($isCheque)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400!">Cheque cleared</span>
                        <span>{{ $payment->cheque_cleared_at?->format('d M Y') ?? 'Not yet cleared' }}</span>
                    </div>
                @endif
                <div class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400!">Verified</span>
                    <span>{{ $payment->verified_at?->format('d M Y H:i') ?? '—' }}</span>
                </div>
                @if ($payment->proof_path)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400!">Proof of payment</span>
                        <a href="{{ route('payments.proof', $payment) }}" target="_blank" class="text-[color:var(--ts-primary)] hover:underline">View</a>
                    </div>
                @endif
                @if ($payment->notes)
                    <div class="pt-1 border-t border-gray-200 dark:border-gray-800! mt-1">
                        <span class="text-gray-500 dark:text-gray-400!">Notes</span>
                        <p class="mt-1">{{ $payment->notes }}</p>
                    </div>
                @endif
            </div>
        </x-card>
    </div>

    {{-- Allocate / Amend allocation modal — a dynamic row list, exactly
         mirroring PaymentsTable's own Filament Repeater (including the
         duplicate-invoice-id summing behavior — see mapAllocations() in
         the component class). --}}
    <x-modal wire="showAllocationModal" title="{{ $payment->receipt ? 'Amend allocation' : 'Allocate to invoices' }}" center="md" scrollable>
        <div class="flex flex-col gap-4">
            @if ($payment->receipt)
                <x-textarea wire:model="amend_reason" label="Reason" rows="2" required hint="Required — a receipt already exists for this payment; this preserves its original snapshot and records a linked amendment." />
            @endif

            <div class="flex flex-col gap-3">
                @foreach ($allocationRows as $i => $row)
                    <div class="grid grid-cols-1! sm:grid-cols-[1fr_140px_auto]! gap-2 items-end">
                        <x-select.styled wire:model="allocationRows.{{ $i }}.invoice_id" label="Invoice" searchable :options="$invoiceOptions" />
                        <x-input wire:model="allocationRows.{{ $i }}.amount" label="Amount" type="number" step="0.01" />
                        <x-button icon="trash" color="red" scope="icon-action" class="h-9 w-9" wire:click="removeAllocationRow({{ $i }})" />
                    </div>
                @endforeach
            </div>

            <x-button text="Add invoice" icon="plus" color="gray" sm wire:click="addAllocationRow" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showAllocationModal', false)" />
            <x-button text="Save allocation" color="blue" wire:click="saveAllocation" loading="saveAllocation" spinner="dots" />
        </x-slot:footer>
    </x-modal>

    {{-- Verify — a cheque requires a cleared date before it can verify. --}}
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
