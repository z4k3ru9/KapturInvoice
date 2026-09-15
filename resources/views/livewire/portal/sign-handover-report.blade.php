@php
    $job = $handoverReport->salesOrder;
    $logo = $handoverReport->company->getLogoDataUri();
@endphp

<div class="min-h-screen bg-gray-50 dark:bg-gray-950!">
    <header class="border-b border-gray-200 bg-white dark:border-gray-800! dark:bg-gray-900!">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-3">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $handoverReport->company->name }}" class="h-9 w-auto">
                @endif
                <div>
                    <span class="block text-lg font-semibold leading-tight">{{ $handoverReport->company->name }}</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">Handover confirmation</span>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-3xl space-y-6 px-6 py-10">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Handover Report {{ $handoverReport->number }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                For {{ $job?->client?->name ?? '—' }} · job {{ $job?->number ?? '—' }}
                @if ($handoverReport->handover_date)
                    · handed over {{ $handoverReport->handover_date->toFormattedDateString() }}
                @endif
            </p>
        </div>

        @if ($handoverReport->notes)
            <x-card>
                <p class="text-sm whitespace-pre-line">{{ $handoverReport->notes }}</p>
            </x-card>
        @endif

        <x-card header="Confirmation">
            @if ($handoverReport->signed_at)
                <div class="flex items-start gap-2 rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">
                    <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0" />
                    <div>
                        <p>Confirmed by {{ $handoverReport->signed_by_name }} on {{ $handoverReport->signed_at->toFormattedDateString() }}.</p>
                        @if ($handoverReport->hasSignatureImage())
                            <img src="{{ $handoverReport->signature }}" alt="Signature" class="mt-2 h-20 rounded border border-green-200 bg-white">
                        @endif
                    </div>
                </div>
            @else
                <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                    Please confirm this handover by typing your name and drawing your signature below.
                </p>

                <form wire:submit="sign" class="space-y-3">
                    <x-input wire:model="signerName" label="Your name" placeholder="Full name" />

                    {{-- `persistent` is load-bearing — see
                         view-invoice.blade.php's docblock. --}}
                    <x-signature
                        wire:model="capturedSignature"
                        label="Draw your signature to confirm"
                        hint="Draw inside the box below, then Confirm handover."
                        height="200"
                        clearable
                        persistent
                    />
                    <div class="flex justify-end">
                        <x-button type="submit" text="Confirm handover" color="primary" />
                    </div>
                </form>
            @endif
        </x-card>

        <div class="border-t border-gray-200 pt-6 text-center text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
            <p class="font-medium text-gray-600 dark:text-gray-300">{{ $handoverReport->company->name }}</p>
        </div>
    </main>
</div>
