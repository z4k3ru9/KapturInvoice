@php
    $client = $portalLink->client;
    $company = $client->company;
@endphp

<div class="min-h-screen">
    <header class="border-b border-gray-200 dark:border-gray-800">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-3">
                @if ($company->logo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($company->logo_path) }}" alt="{{ $company->name }}" class="h-9 w-auto">
                @endif
                <span class="text-lg font-semibold">{{ $company->name }}</span>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-4xl space-y-6 px-6 py-12">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Billing history</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $client->name }}</p>
        </div>

        <x-card>
            @if ($invoices->isEmpty())
                <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No documents to show yet.</p>
            @else
                {{-- A real WCAG 2.2 AA gap found via the axe-core browser
                     scan (Phase 06B Slice 5, mobile viewport): a
                     horizontally-scrollable container with no way for a
                     keyboard user to actually scroll it (axe:
                     scrollable-region-focusable). `tabindex="0"` plus a
                     real accessible name makes it a focusable, keyboard-
                     scrollable region. --}}
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Billing history table">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                <th class="py-2">Number</th>
                                <th class="py-2">Date</th>
                                <th class="py-2 text-right">Total</th>
                                <th class="py-2 text-right">Balance</th>
                                <th class="py-2">Status</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                @php
                                    $invitation = $invoice->invitations->first();
                                @endphp
                                <tr class="border-b border-gray-100 dark:border-gray-800/60">
                                    <td class="py-2 font-medium">{{ $invoice->number }}</td>
                                    <td class="py-2">{{ $invoice->invoice_date?->toFormattedDateString() ?? '-' }}</td>
                                    <td class="py-2 text-right">{{ $invoice->currency_code }} {{ number_format($invoice->total, 2) }}</td>
                                    <td class="py-2 text-right">{{ $invoice->currency_code }} {{ number_format($invoice->balance, 2) }}</td>
                                    <td class="py-2">
                                        <x-portal.status-badge :label="$invoice->status->getLabel()" :color="$invoice->status->getColor()" />
                                    </td>
                                    <td class="py-2 text-right">
                                        @if ($invitation)
                                            <a href="{{ route('portal.invoice', $invitation) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                                View
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                                @if ($invoice->payments->isNotEmpty())
                                    <tr class="border-b border-gray-100 dark:border-gray-800/60">
                                        <td colspan="6" class="pb-3 pl-4 text-xs text-gray-500 dark:text-gray-400">
                                            <div class="space-y-1">
                                                @foreach ($invoice->payments as $payment)
                                                    <div class="flex justify-between">
                                                        <span>
                                                            {{ $payment->payment_date?->toFormattedDateString() ?? $payment->created_at->toFormattedDateString() }}
                                                            — {{ $payment->method ?: 'Payment' }}
                                                            @if ($payment->receipt)
                                                                (Receipt {{ $payment->receipt->number }})
                                                            @endif
                                                        </span>
                                                        <span class="font-medium">{{ $invoice->currency_code }} {{ number_format($payment->amount, 2) }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card header="Company contact information">
            <dl class="space-y-1 text-sm">
                <div><dt class="inline text-gray-500 dark:text-gray-400">Name:</dt> <dd class="inline">{{ $company->name }}</dd></div>
                @if ($company->address_line_1)
                    <div>
                        <dt class="inline text-gray-500 dark:text-gray-400">Address:</dt>
                        <dd class="inline">
                            {{ $company->address_line_1 }}@if ($company->address_line_2), {{ $company->address_line_2 }}@endif
                            @if ($company->city), {{ $company->city }}@endif
                        </dd>
                    </div>
                @endif
                @if ($company->email)
                    <div><dt class="inline text-gray-500 dark:text-gray-400">Email:</dt> <dd class="inline">{{ $company->email }}</dd></div>
                @endif
                @if ($company->phone)
                    <div><dt class="inline text-gray-500 dark:text-gray-400">Phone:</dt> <dd class="inline">{{ $company->phone }}</dd></div>
                @endif
            </dl>
        </x-card>
    </main>
</div>
