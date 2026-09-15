@php
    $client = $portalLink->client;
    $company = $client->company;
    $currency = $invoices->first()?->currency_code;
    $outstanding = $invoices->sum('balance');
    $openCount = $invoices->where('balance', '>', 0)->count();
    $logo = $company->getLogoDataUri();
@endphp

{{--
    dark:bg-* on this page's own wrapper/header uses `!` important — see
    view-invoice.blade.php's identical comment (same root cause as
    status-badge.blade.php's docblock).
--}}
<div class="min-h-screen bg-gray-50 dark:bg-gray-950!">
    <header class="border-b border-gray-200 bg-white dark:border-gray-800! dark:bg-gray-900!">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-3">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $company->name }}" class="h-9 w-auto">
                @endif
                <div>
                    <span class="block text-lg font-semibold leading-tight">{{ $company->name }}</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">Client billing portal</span>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl space-y-6 px-6 py-10">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Billing history</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $client->name }}</p>
        </div>

        @if ($invoices->isNotEmpty())
            {{-- The value spans below are pinned to text-gray-900 with no
                 dark: variant — see view-invoice.blade.php's identical
                 comment for why (<x-stats>'s card background never
                 actually switches to dark). --}}
            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                <x-stats scope="compact" title="Outstanding balance" icon="banknotes" :color="$outstanding > 0 ? 'amber' : 'green'">
                    <span class="text-lg font-bold tabular-nums text-gray-900">{{ $currency }} {{ number_format($outstanding, 2) }}</span>
                    <x-slot:footer>{{ $openCount }} document{{ $openCount === 1 ? '' : 's' }} with a balance</x-slot:footer>
                </x-stats>

                <x-stats scope="compact" title="Documents" icon="document-text" color="blue">
                    <span class="text-lg font-bold tabular-nums text-gray-900">{{ $invoices->count() }}</span>
                    <x-slot:footer>Shown on this portal link</x-slot:footer>
                </x-stats>

                <x-stats scope="compact" title="Access" icon="user" color="gray">
                    <span class="text-lg font-bold text-gray-900">{{ $portalLink->contact->is_billing_contact ? 'Full billing history' : 'Shared documents' }}</span>
                    <x-slot:footer>{{ $portalLink->contact->name }}</x-slot:footer>
                </x-stats>
            </div>
        @endif

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
                {{--
                    Kept as a plain, hand-styled table rather than
                    TallStackUI's <x-table> here specifically: every
                    invoice row needs an extra, non-tabular "payment
                    events" sub-row directly beneath it (see
                    paymentEventsFor() below), which <x-table> has no
                    supported way to interleave — its `expandable`/
                    `sub_table` mechanism is a toggled collapse panel, not
                    an always-visible second row, and would hide payment
                    history behind an extra click for no benefit. The
                    line-items/payment-history tables on
                    view-invoice.blade.php use <x-table> where that flat
                    shape fits cleanly.
                --}}
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Billing history table">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-900/60 dark:text-gray-400">
                                <th class="px-3 py-2">Number</th>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2 text-right">Total</th>
                                <th class="px-3 py-2 text-right">Balance</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                @php
                                    $invitation = $invoice->invitations->first();
                                @endphp
                                <tr class="border-b border-gray-100 dark:border-gray-800/60">
                                    <td class="px-3 py-2.5 font-medium">{{ $invoice->number }}</td>
                                    <td class="px-3 py-2.5">{{ $invoice->invoice_date?->toFormattedDateString() ?? '-' }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ $invoice->currency_code }} {{ number_format($invoice->total, 2) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ $invoice->currency_code }} {{ number_format($invoice->balance, 2) }}</td>
                                    <td class="px-3 py-2.5">
                                        <x-portal.status-badge :label="$invoice->status->getLabel()" :color="$invoice->status->getColor()" />
                                    </td>
                                    <td class="px-3 py-2.5 text-right">
                                        @if ($invitation)
                                            <a href="{{ route('portal.invoice', $invitation) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                                View
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                                @php $paymentEvents = $this->paymentEventsFor($invoice); @endphp
                                @if (! empty($paymentEvents))
                                    <tr class="border-b border-gray-100 dark:border-gray-800/60">
                                        <td colspan="6" class="px-3 pb-3 pl-6 text-xs text-gray-500 dark:text-gray-400">
                                            <div class="space-y-1">
                                                @foreach ($paymentEvents as $event)
                                                    <div class="flex justify-between">
                                                        <span>
                                                            {{ $event['date']?->toFormattedDateString() ?? '-' }}
                                                            — {{ $event['method'] ?: 'Payment' }}
                                                            @if ($event['receipt_number'])
                                                                (Receipt {{ $event['receipt_number'] }})
                                                            @endif
                                                        </span>
                                                        <span class="font-medium tabular-nums">{{ $invoice->currency_code }} {{ number_format($event['amount'], 2) }}</span>
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
                    <div><dt class="inline text-gray-500 dark:text-gray-400">Email:</dt> <dd class="inline"><a href="mailto:{{ $company->email }}" class="hover:underline">{{ $company->email }}</a></dd></div>
                @endif
                @if ($company->phone)
                    <div><dt class="inline text-gray-500 dark:text-gray-400">Phone:</dt> <dd class="inline"><a href="tel:{{ $company->phone }}" class="hover:underline">{{ $company->phone }}</a></dd></div>
                @endif
            </dl>
        </x-card>
    </main>
</div>
