@php
    $job = $deliveryOrder->salesOrder;
    $logo = $deliveryOrder->company->getLogoDataUri();
@endphp

{{-- dark: backgrounds/borders use `!important` — see
     resources/views/components/portal/status-badge.blade.php's docblock
     for the established root cause, same as view-invoice.blade.php. --}}
<div class="min-h-screen bg-gray-50 dark:bg-gray-950!">
    <header class="border-b border-gray-200 bg-white dark:border-gray-800! dark:bg-gray-900!">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-3">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $deliveryOrder->company->name }}" class="h-9 w-auto">
                @endif
                <div>
                    <span class="block text-lg font-semibold leading-tight">{{ $deliveryOrder->company->name }}</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400!">Delivery confirmation</span>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-3xl space-y-6 px-6 py-10">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Delivery Order {{ $deliveryOrder->number }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400!">
                For {{ $job?->client?->name ?? '—' }}
                @if ($deliveryOrder->delivery_date)
                    · delivered {{ $deliveryOrder->delivery_date->toFormattedDateString() }}
                @endif
            </p>
        </div>

        <x-card>
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Delivered items table">
                <x-table :headers="[
                    ['index' => 'title', 'label' => 'Item'],
                    ['index' => 'quantity_delivered', 'label' => 'Qty delivered', 'align' => 'right'],
                ]" :rows="$deliveryOrder->items">
                    @interact('column_title', $row)
                        <div class="font-medium">{{ $row->salesOrderItem?->title ?? $row->description ?? '—' }}</div>
                        @if ($row->description && $row->salesOrderItem?->title)
                            <div class="text-sm text-gray-500 dark:text-gray-400!">{{ $row->description }}</div>
                        @endif
                    @endinteract

                    @interact('column_quantity_delivered', $row)
                        <span class="tabular-nums">{{ rtrim(rtrim(number_format((float) $row->quantity_delivered, 4), '0'), '.') ?: '0' }}</span>
                    @endinteract

                    <x-slot:empty>No lines recorded on this delivery order.</x-slot:empty>
                </x-table>
            </div>

            @if ($deliveryOrder->notes)
                <div class="mt-4 border-t border-gray-200 pt-4 text-sm text-gray-600 dark:border-gray-800! dark:text-gray-400!">
                    <p class="whitespace-pre-line">{{ $deliveryOrder->notes }}</p>
                </div>
            @endif
        </x-card>

        <x-card header="Confirmation">
            @if ($deliveryOrder->signed_at)
                <div class="flex items-start gap-2 rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30! dark:text-green-300!">
                    <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0" />
                    <div>
                        <p>Confirmed by {{ $deliveryOrder->signed_by_name }} on {{ $deliveryOrder->signed_at->toFormattedDateString() }}.</p>
                        @if ($deliveryOrder->hasSignatureImage())
                            <img src="{{ $deliveryOrder->signature }}" alt="Signature" class="mt-2 h-20 rounded border border-green-200 bg-white">
                        @endif
                    </div>
                </div>
            @else
                <p class="mb-3 text-sm text-gray-600 dark:text-gray-400!">
                    Please confirm receipt of this delivery by typing your name and drawing your signature below.
                </p>

                <form wire:submit="sign" class="space-y-3">
                    <x-input wire:model="signerName" label="Your name" placeholder="Full name" />

                    {{-- `persistent` is load-bearing here too — see
                         view-invoice.blade.php's docblock for the full
                         ResizeObserver-clears-the-canvas finding this
                         guards against. --}}
                    <x-signature
                        wire:model="capturedSignature"
                        label="Draw your signature to confirm"
                        hint="Draw inside the box below, then Confirm delivery."
                        height="200"
                        clearable
                        persistent
                    />
                    <div class="flex justify-end">
                        <x-button type="submit" text="Confirm delivery" color="primary" />
                    </div>
                </form>
            @endif
        </x-card>

        <div class="border-t border-gray-200 pt-6 text-center text-xs text-gray-500 dark:border-gray-800! dark:text-gray-400!">
            <p class="font-medium text-gray-600 dark:text-gray-300!">{{ $deliveryOrder->company->name }}</p>
        </div>
    </main>
</div>
