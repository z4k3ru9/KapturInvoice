@php
    $invoice = $invitation->invoice;
    $settings = $invoice->company->settings;
@endphp

<div class="min-h-screen">
    <header class="border-b border-gray-200 dark:border-gray-800">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-3">
                @if ($invoice->company->logo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($invoice->company->logo_path) }}" alt="{{ $invoice->company->name }}" class="h-9 w-auto">
                @endif
                <span class="text-lg font-semibold">{{ $invoice->company->name }}</span>
            </div>

            <x-portal.status-badge :label="$invoice->status->getLabel()" :color="$invoice->status->getColor()" />
        </div>
    </header>

    <main class="mx-auto max-w-4xl space-y-6 px-6 py-12">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    {{ $invoice->type->getLabel() }} {{ $invoice->number }}
                </h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Billed to {{ $invoice->client->name }}
                    @if ($invitation->contact->name)
                        (attn: {{ $invitation->contact->name }})
                    @endif
                </p>
                <a href="{{ route('portal.invoice.pdf', $invitation) }}" target="_blank" class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                    Download PDF
                </a>
            </div>

            <dl class="text-right text-sm">
                @if ($invoice->invoice_date)
                    <div><dt class="inline text-gray-500 dark:text-gray-400">Date:</dt> <dd class="inline">{{ $invoice->invoice_date->toFormattedDateString() }}</dd></div>
                @endif
                @if ($invoice->due_date)
                    <div><dt class="inline text-gray-500 dark:text-gray-400">Due:</dt> <dd class="inline">{{ $invoice->due_date->toFormattedDateString() }}</dd></div>
                @endif
            </dl>
        </div>

        <x-card>
            {{-- See client-portal-home.blade.php for why this needs
                 tabindex/role/aria-label (axe: scrollable-region-focusable,
                 found on mobile viewports). --}}
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Invoice items table">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="py-2">Item</th>
                            <th class="py-2 text-right">Qty</th>
                            <th class="py-2 text-right">Unit price</th>
                            <th class="py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->items as $item)
                            <tr class="border-b border-gray-100 dark:border-gray-800/60">
                                <td class="py-2">
                                    <div class="font-medium">{{ $item->title }}</div>
                                    @if ($item->description)
                                        <div class="text-gray-500 dark:text-gray-400">{{ $item->description }}</div>
                                    @endif
                                </td>
                                <td class="py-2 text-right">{{ rtrim(rtrim($item->quantity, '0'), '.') ?: '0' }}</td>
                                <td class="py-2 text-right">{{ number_format($item->unit_cost, 2) }}</td>
                                <td class="py-2 text-right">{{ number_format($item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-end">
                <dl class="w-full max-w-xs space-y-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Subtotal</dt>
                        <dd>{{ $invoice->currency_code }} {{ number_format($invoice->subtotal, 2) }}</dd>
                    </div>
                    @if ($invoice->tax_total > 0)
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Tax</dt>
                            <dd>{{ $invoice->currency_code }} {{ number_format($invoice->tax_total, 2) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between border-t border-gray-200 pt-1 text-base font-semibold dark:border-gray-800">
                        <dt>Total</dt>
                        <dd>{{ $invoice->currency_code }} {{ number_format($invoice->total, 2) }}</dd>
                    </div>
                    @if ($invoice->balance > 0 && $invoice->balance != $invoice->total)
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Balance due</dt>
                            <dd class="font-medium">{{ $invoice->currency_code }} {{ number_format($invoice->balance, 2) }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </x-card>

        @if ($invoice->balance > 0)
            <x-card header="Payment">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Balance due: <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $invoice->currency_code }} {{ number_format($invoice->balance, 2) }}</span>
                </p>
                @if ($settings?->portal_allow_client_payments)
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Online payment isn't available on this portal yet — please contact {{ $invoice->company->name }} to arrange payment.
                    </p>
                @endif
            </x-card>
        @endif

        @if ($invoice->payments->isNotEmpty())
            <x-card header="Payment history">
                <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-800/60">
                    @foreach ($invoice->payments as $payment)
                        <li class="flex justify-between py-2">
                            <span>{{ $payment->payment_date?->toFormattedDateString() ?? $payment->created_at->toFormattedDateString() }} — {{ $payment->method ?: 'Payment' }}</span>
                            <span class="font-medium">{{ $invoice->currency_code }} {{ number_format($payment->amount, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        @if ($invoice->public_notes || $invoice->terms)
            <x-card>
                @if ($invoice->public_notes)
                    <p class="text-sm whitespace-pre-line">{{ $invoice->public_notes }}</p>
                @endif
                @if ($invoice->terms)
                    <p class="mt-3 text-sm whitespace-pre-line text-gray-500 dark:text-gray-400">{{ $invoice->terms }}</p>
                @endif
            </x-card>
        @endif

        @if ($invoice->documents->isNotEmpty())
            <x-card header="Attachments">
                <ul class="space-y-2 text-sm">
                    @foreach ($invoice->documents as $document)
                        <li>{{ $document->filename }}</li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <x-card header="Acceptance">
            @if ($invitation->signed_at)
                <div class="rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">
                    Signed by <strong>{{ $invitation->signature }}</strong> on {{ $invitation->signed_at->toFormattedDateString() }}.
                </div>
            @else
                @if ($settings?->portal_require_signature)
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                        A signature is required to accept this {{ strtolower($invoice->type->getLabel()) }}.
                    </p>
                @endif

                <form wire:submit="sign" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-64 flex-1">
                        <x-input label="Type your full name to sign" wire:model="signatureName" placeholder="Jane Doe" />
                    </div>
                    <x-button type="submit" text="Accept & sign" color="primary" />
                </form>
            @endif
        </x-card>
    </main>
</div>
