@php
    $invoice = $invitation->invoice;
    $settings = $invoice->company->settings;
    $lastPayment = $invoice->payments->sortByDesc(fn ($payment) => $payment->payment_date ?? $payment->created_at)->first();
    $logo = $invoice->company->getLogoDataUri();
@endphp

{{--
    dark: backgrounds/borders on this page's own wrapper elements use the
    `!` important modifier — see resources/views/components/portal/
    status-badge.blade.php's docblock for the established root cause: a
    plain `dark:bg-*`/`dark:border-*` utility from this app's own
    compiled CSS (resources/css/app.css) doesn't reliably outrank
    TallStackUI's own later-loaded stylesheet (@tallStackUiStyle) at
    equal specificity, so the light-mode rule silently wins even while
    `prefers-color-scheme: dark` is genuinely active.
--}}
<div class="min-h-screen bg-gray-50 dark:bg-gray-950!">
    <header class="border-b border-gray-200 bg-white dark:border-gray-800! dark:bg-gray-900!">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-3">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $invoice->company->name }}" class="h-9 w-auto">
                @endif
                <div>
                    <span class="block text-lg font-semibold leading-tight">{{ $invoice->company->name }}</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">Client billing portal</span>
                </div>
            </div>

            <x-portal.status-badge :label="$invoice->status->getLabel()" :color="$invoice->status->getColor()" />
        </div>
    </header>

    <main class="mx-auto max-w-5xl space-y-6 px-6 py-10">
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
            </div>

            <div class="flex items-center gap-3">
                <dl class="text-right text-sm">
                    @if ($invoice->invoice_date)
                        <div><dt class="inline text-gray-500 dark:text-gray-400">Date:</dt> <dd class="inline">{{ $invoice->invoice_date->toFormattedDateString() }}</dd></div>
                    @endif
                    @if ($invoice->due_date)
                        <div><dt class="inline text-gray-500 dark:text-gray-400">Due:</dt> <dd class="inline">{{ $invoice->due_date->toFormattedDateString() }}</dd></div>
                    @endif
                </dl>
                <x-button text="Download PDF" icon="arrow-down-tray" href="{{ route('portal.invoice.pdf', $invitation) }}" target="_blank" sm color="gray" />
            </div>
        </div>

        {{-- Quiet billing overview — DESIGN.md §11: a small, calm stat row
             built only from data this invoice already carries, not the
             mockup's client-wide "Active Deployments"/"Escrow Credit"
             tiles (no data model for either).

             The value spans below are pinned to text-gray-900 with no
             dark: variant on purpose: <x-stats>'s own card background
             never actually switches to a dark palette (TallStackUI's
             pre-compiled CSS gates its own dark: styling behind a
             literal `.dark` class this app never toggles — see
             layouts/public.blade.php's docblock for the same root cause
             on this app's own utilities), so it stays light in both
             themes; inheriting this page's forced-white body text color
             here would render white-on-white. --}}
        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
            <x-stats scope="compact" title="Balance due" icon="banknotes" :color="$invoice->balance > 0 ? 'amber' : 'green'">
                <span class="text-lg font-bold tabular-nums text-gray-900">{{ $invoice->currency_code }} {{ number_format($invoice->balance, 2) }}</span>
                <x-slot:footer>
                    @if ($invoice->due_date)
                        Due {{ $invoice->due_date->toFormattedDateString() }}
                    @else
                        No due date set
                    @endif
                </x-slot:footer>
            </x-stats>

            <x-stats scope="compact" title="Total" icon="document-text" color="blue">
                <span class="text-lg font-bold tabular-nums text-gray-900">{{ $invoice->currency_code }} {{ number_format($invoice->total, 2) }}</span>
                <x-slot:footer>{{ $invoice->items->count() }} line item{{ $invoice->items->count() === 1 ? '' : 's' }}</x-slot:footer>
            </x-stats>

            <x-stats scope="compact" title="Last payment" icon="check-circle" color="green">
                @if ($lastPayment)
                    <span class="text-lg font-bold tabular-nums text-gray-900">{{ $invoice->currency_code }} {{ number_format($lastPayment->amount, 2) }}</span>
                    <x-slot:footer>{{ ($lastPayment->payment_date ?? $lastPayment->created_at)->toFormattedDateString() }}</x-slot:footer>
                @else
                    <span class="text-lg font-bold text-gray-400 dark:text-gray-600">None yet</span>
                    <x-slot:footer>&nbsp;</x-slot:footer>
                @endif
            </x-stats>
        </div>

        <x-card>
            {{-- See client-portal-home.blade.php for why this needs
                 tabindex/role/aria-label (axe: scrollable-region-focusable,
                 found on mobile viewports). --}}
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Invoice items table">
                <x-table :headers="[
                    ['index' => 'title', 'label' => 'Item'],
                    ['index' => 'quantity', 'label' => 'Qty', 'align' => 'right'],
                    ['index' => 'unit_cost', 'label' => 'Unit price', 'align' => 'right'],
                    ['index' => 'line_total', 'label' => 'Total', 'align' => 'right'],
                ]" :rows="$invoice->items">
                    @interact('column_title', $row)
                        <div class="font-medium">{{ $row->title }}</div>
                        @if ($row->description)
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $row->description }}</div>
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
                </x-table>
            </div>

            <div class="mt-4 flex justify-end">
                <dl class="w-full max-w-xs space-y-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Subtotal</dt>
                        <dd class="tabular-nums">{{ $invoice->currency_code }} {{ number_format($invoice->subtotal, 2) }}</dd>
                    </div>
                    @if ($invoice->tax_total > 0)
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Tax</dt>
                            <dd class="tabular-nums">{{ $invoice->currency_code }} {{ number_format($invoice->tax_total, 2) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between border-t border-gray-200 pt-1 text-base font-semibold dark:border-gray-800">
                        <dt>Total</dt>
                        <dd class="tabular-nums">{{ $invoice->currency_code }} {{ number_format($invoice->total, 2) }}</dd>
                    </div>
                    @if ($invoice->balance > 0 && $invoice->balance != $invoice->total)
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Balance due</dt>
                            <dd class="font-medium tabular-nums">{{ $invoice->currency_code }} {{ number_format($invoice->balance, 2) }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </x-card>

        @if ($invoice->balance > 0)
            <x-card header="Payment">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Balance due: <span class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $invoice->currency_code }} {{ number_format($invoice->balance, 2) }}</span>
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
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Payment history table">
                    <x-table :headers="[
                        ['index' => 'date', 'label' => 'Date'],
                        ['index' => 'method', 'label' => 'Method'],
                        ['index' => 'amount', 'label' => 'Amount', 'align' => 'right'],
                    ]" :rows="$invoice->payments" headerless>
                        @interact('column_date', $row)
                            {{ ($row->payment_date ?? $row->created_at)->toFormattedDateString() }}
                        @endinteract

                        @interact('column_method', $row)
                            {{ $row->method ?: 'Payment' }}
                        @endinteract

                        @interact('column_amount', $row, $invoice)
                            <span class="font-medium tabular-nums">{{ $invoice->currency_code }} {{ number_format($row->amount, 2) }}</span>
                        @endinteract
                    </x-table>
                </div>
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
                        <li class="flex items-center gap-2">
                            <x-icon name="paper-clip" class="h-4 w-4 shrink-0 text-gray-400" />
                            {{ $document->filename }}
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <x-card header="Acceptance">
            @if ($invitation->signed_at)
                <div class="flex items-start gap-2 rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">
                    <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0" />
                    <div>
                        <p>Signed on {{ $invitation->signed_at->toFormattedDateString() }}.</p>
                        @if ($invitation->hasSignatureImage())
                            <img src="{{ $invitation->signature }}" alt="Signature" class="mt-2 h-20 rounded border border-green-200 bg-white">
                        @elseif ($invitation->signature)
                            <p class="mt-1">Signed by <strong>{{ $invitation->signature }}</strong>.</p>
                        @endif
                    </div>
                </div>
            @else
                @if ($settings?->portal_require_signature)
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                        A signature is required to accept this {{ strtolower($invoice->type->getLabel()) }}.
                    </p>
                @endif

                <form wire:submit="sign" class="space-y-3">
                    {{--
                        `persistent` is load-bearing, not decorative: the
                        signature canvas otherwise wipes its drawing (and
                        clears the entangled model back to null) on any
                        layout shift that nudges its container's width —
                        a late web-font swap, an image reflow, a mobile
                        keyboard opening — even after the signer has
                        finished drawing. Confirmed live via Playwright:
                        without this, a completed drawing (verified via
                        the canvas's own pixel data right after the
                        stroke) was silently cleared ~300ms later by the
                        component's own ResizeObserver, well before the
                        signer could click Accept & sign.
                    --}}
                    <x-signature
                        wire:model="capturedSignature"
                        label="Draw your signature to sign"
                        hint="Draw inside the box below, then Accept & sign."
                        height="200"
                        clearable
                        persistent
                    />
                    <div class="flex justify-end">
                        <x-button type="submit" text="Accept & sign" color="primary" />
                    </div>
                </form>
            @endif
        </x-card>

        <div class="border-t border-gray-200 pt-6 text-center text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
            <p class="font-medium text-gray-600 dark:text-gray-300">{{ $invoice->company->name }}</p>
            <p class="mt-1 space-x-3">
                @if ($invoice->company->email)
                    <a href="mailto:{{ $invoice->company->email }}" class="hover:underline">{{ $invoice->company->email }}</a>
                @endif
                @if ($invoice->company->phone)
                    <a href="tel:{{ $invoice->company->phone }}" class="hover:underline">{{ $invoice->company->phone }}</a>
                @endif
            </p>
        </div>
    </main>
</div>
