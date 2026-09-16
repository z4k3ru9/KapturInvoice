@php
    $logo = $quotation->company->getLogoDataUri();
    $canAccept = $quotation->status->canTransitionTo(\App\Enums\QuotationStatus::Accepted);
@endphp

<div class="min-h-screen bg-gray-50 dark:bg-gray-950!">
    <header class="border-b border-gray-200 bg-white dark:border-gray-800! dark:bg-gray-900!">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-3">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $quotation->company->name }}" class="h-9 w-auto">
                @endif
                <div>
                    <span class="block text-lg font-semibold leading-tight">{{ $quotation->company->name }}</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400!">Quotation approval</span>
                </div>
            </div>

            <x-portal.status-badge :label="$quotation->status->getLabel()" :color="$quotation->status->getColor()" />
        </div>
    </header>

    <main class="mx-auto max-w-3xl space-y-6 px-6 py-10">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Quotation {{ $quotation->number ?? '—' }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400!">
                Prepared for {{ $quotation->client->name }}
                @if ($quotation->valid_until)
                    · valid until {{ $quotation->valid_until->toFormattedDateString() }}
                @endif
            </p>
        </div>

        <x-card>
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Quotation items table">
                <x-table :headers="[
                    ['index' => 'title', 'label' => 'Item'],
                    ['index' => 'quantity', 'label' => 'Qty', 'align' => 'right'],
                    ['index' => 'unit_cost', 'label' => 'Unit price', 'align' => 'right'],
                    ['index' => 'line_total', 'label' => 'Total', 'align' => 'right'],
                ]" :rows="$quotation->items">
                    @interact('column_title', $row)
                        <div class="font-medium">{{ $row->title }}</div>
                        @if ($row->description)
                            <div class="text-sm text-gray-500 dark:text-gray-400!">{{ $row->description }}</div>
                        @endif
                    @endinteract

                    @interact('column_quantity', $row)
                        <span class="tabular-nums">{{ rtrim(rtrim($row->quantity, '0'), '.') ?: '0' }}</span>
                    @endinteract

                    @interact('column_unit_cost', $row)
                        <span class="tabular-nums">{{ number_format($row->unit_cost, 2) }}</span>
                    @endinteract

                    @interact('column_line_total', $row)
                        <span class="tabular-nums">{{ number_format($row->line_total, 2) }}</span>
                    @endinteract

                    <x-slot:empty>No line items on this quotation.</x-slot:empty>
                </x-table>
            </div>

            <div class="mt-4 flex justify-end">
                <dl class="w-full max-w-xs space-y-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400!">Subtotal</dt>
                        <dd class="tabular-nums">{{ number_format($quotation->subtotal, 2) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 pt-1 text-base font-semibold dark:border-gray-800!">
                        <dt>Total</dt>
                        <dd class="tabular-nums">{{ number_format($quotation->total, 2) }}</dd>
                    </div>
                </dl>
            </div>
        </x-card>

        @if ($quotation->terms || $quotation->notes)
            <x-card>
                @if ($quotation->notes)
                    <p class="text-sm whitespace-pre-line">{{ $quotation->notes }}</p>
                @endif
                @if ($quotation->terms)
                    <p class="mt-3 text-sm whitespace-pre-line text-gray-500 dark:text-gray-400!">{{ $quotation->terms }}</p>
                @endif
            </x-card>
        @endif

        <x-card header="Approval">
            @if ($quotation->signed_at)
                <div class="flex items-start gap-2 rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30! dark:text-green-300!">
                    <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0" />
                    <div>
                        <p>Accepted by {{ $quotation->signed_by_name }} on {{ $quotation->signed_at->toFormattedDateString() }}.</p>
                        @if ($quotation->customer_po_number)
                            <p class="mt-1">
                                {{ $quotation->customer_po_is_system_generated ? 'Customer Order Confirmation' : 'Customer PO' }}:
                                <strong>{{ $quotation->customer_po_number }}</strong>
                            </p>
                        @endif
                        @if ($quotation->hasSignatureImage())
                            <img src="{{ $quotation->signature }}" alt="Signature" class="mt-2 h-20 rounded border border-green-200 bg-white">
                        @endif
                    </div>
                </div>
            @elseif ($canAccept)
                <p class="mb-3 text-sm text-gray-600 dark:text-gray-400!">
                    Please review the quotation above, then accept it by typing your name and drawing your signature
                    below. If you have a purchase order number for this job, enter it — otherwise leave it blank and
                    an internal Customer Order Confirmation will be generated on your behalf.
                </p>

                <form wire:submit="accept" class="space-y-3">
                    <x-input wire:model="signerName" label="Your name" placeholder="Full name" />
                    <x-input wire:model="customerPoNumber" label="Your PO number (optional)" />
                    <x-date wire:model="customerPoDate" label="PO date (optional)" />

                    {{-- `persistent` is load-bearing — see
                         view-invoice.blade.php's docblock. --}}
                    <x-signature
                        wire:model="capturedSignature"
                        label="Draw your signature to accept"
                        hint="Draw inside the box below, then Accept quotation."
                        height="200"
                        clearable
                        persistent
                    />
                    <div class="flex justify-end">
                        <x-button type="submit" text="Accept quotation" color="primary" />
                    </div>
                </form>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-400!">
                    This quotation is not currently available for acceptance
                    (status: {{ $quotation->status->getLabel() }}).
                </p>
            @endif
        </x-card>

        <div class="border-t border-gray-200 pt-6 text-center text-xs text-gray-500 dark:border-gray-800! dark:text-gray-400!">
            <p class="font-medium text-gray-600 dark:text-gray-300!">{{ $quotation->company->name }}</p>
        </div>
    </main>
</div>
