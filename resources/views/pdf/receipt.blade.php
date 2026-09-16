@php
    // Printed documents default to Bahasa Indonesia with a per-document
    // English override — see App\Models\Receipt::resolveDocumentLanguage()
    // and docs/rebuild/specs/06b-ux-browser-soa/Specs.md "Required launch
    // document coverage".
    app()->setLocale($receipt->resolveDocumentLanguage());

    $payment = $receipt->payment;
    $client = $payment->client;

    // Render from the frozen `snapshot` captured at issuance
    // (App\Actions\Receivables\IssuePaymentReceipt) — never the payment's
    // live, potentially-since-amended allocations. A receipt issued
    // before this snapshot existed falls back to the live data it always
    // rendered from, rather than showing blank amounts.
    $snapshot = $receipt->snapshot;
    $amount = $snapshot['amount'] ?? $payment->amount;
    $currencyCode = $snapshot['currency_code'] ?? $payment->currency_code;
    $method = $snapshot['method'] ?? $payment->method;
    $reference = $snapshot['reference'] ?? $payment->reference;
    $allocationRows = $snapshot['allocations']
        ?? $payment->allocations->map(fn ($allocation) => [
            'invoice_number' => $allocation->invoice?->number,
            'amount' => $allocation->amount,
            'is_active' => $allocation->is_active,
        ])->all();
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('documents.type_receipt') }} {{ $receipt->number }}</title>
    <style>
        @include('pdf.partials.page-footer')
        {{-- dompdf has limited CSS support (no flexbox/grid) — plain
             block/table layout only, per docs/filament-admin-layout-design.md §7. --}}
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1f2937; }
        .header { width: 100%; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .company-name { font-size: 18px; font-weight: bold; }
        .doc-title { font-size: 20px; font-weight: bold; text-align: right; }
        .doc-meta { text-align: right; color: #6b7280; }
        .muted { color: #6b7280; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.items th { text-align: left; border-bottom: 2px solid #1f2937; padding: 6px 4px; font-size: 11px; text-transform: uppercase; color: #6b7280; }
        table.items td { padding: 6px 4px; border-bottom: 1px solid #e5e7eb; }
        table.items tbody tr:nth-child(even) { background-color: #f9fafb; }
        .text-right { text-align: right; }
        table.totals { width: 260px; margin-left: auto; margin-top: 12px; }
        table.totals td { padding: 3px 4px; }
        table.totals tr.total td { font-weight: bold; font-size: 14px; border-top: 2px solid #1f2937; }
        .notes { margin-top: 24px; }
        .notes h4 { margin-bottom: 4px; color: #6b7280; font-size: 11px; text-transform: uppercase; }
        .logo { max-height: 48px; max-width: 220px; margin-bottom: 6px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="50%">
                @if ($logoDataUri = $receipt->company->getLogoDataUri())
                    <img class="logo" src="{{ $logoDataUri }}" alt="{{ $receipt->company->name }}">
                @endif
                <div class="company-name">{{ $receipt->company->name }}</div>
                @if ($receipt->company->address_line_1)
                    <div class="muted">{{ $receipt->company->address_line_1 }}</div>
                @endif
                @if ($receipt->company->email)
                    <div class="muted">{{ $receipt->company->email }}</div>
                @endif
                @if ($receipt->company->tax_number)
                    <div class="muted">{{ __('documents.tax_id') }}: {{ $receipt->company->tax_number }}</div>
                @endif
            </td>
            <td width="50%">
                <div class="doc-title">{{ strtoupper(__('documents.type_receipt')) }}</div>
                <div class="doc-meta">{{ $receipt->number }}</div>
                @if ($receipt->issued_at)
                    <div class="doc-meta">{{ __('documents.date') }}: {{ $receipt->issued_at->toFormattedDateString() }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div>
        <h4 class="muted" style="margin-bottom: 2px; text-transform: uppercase; font-size: 11px;">{{ __('documents.receipt_received_from') }}</h4>
        <div style="font-weight: bold;">{{ $client->name }}</div>
        @if ($client->address_line_1)
            <div class="muted">{{ $client->address_line_1 }}</div>
        @endif
        @if ($client->email)
            <div class="muted">{{ $client->email }}</div>
        @endif
        @if ($client->tax_number)
            <div class="muted">{{ __('documents.tax_id') }}: {{ $client->tax_number }}</div>
        @endif
    </div>

    <table class="totals">
        <tr>
            <td>{{ __('documents.receipt_method') }}</td>
            <td class="text-right">{{ $method }}</td>
        </tr>
        @if ($reference)
            <tr>
                <td>{{ __('documents.receipt_reference') }}</td>
                <td class="text-right">{{ $reference }}</td>
            </tr>
        @endif
        <tr class="total">
            <td>{{ __('documents.receipt_amount') }}</td>
            <td class="text-right">{{ $currencyCode }} {{ number_format($amount, 2) }}</td>
        </tr>
    </table>

    <div class="notes">
        <h4>{{ __('documents.receipt_allocated_invoices') }}</h4>
        <table class="items">
            <thead>
                <tr>
                    <th>{{ __('documents.receipt_invoice_number') }}</th>
                    <th class="text-right">{{ __('documents.receipt_allocated_amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($allocationRows as $row)
                    <tr>
                        <td>
                            {{ $row['invoice_number'] }}
                            @if (! $row['is_active'])
                                <span class="muted">({{ __('documents.receipt_allocation_superseded') }})</span>
                            @endif
                        </td>
                        <td class="text-right">{{ $currencyCode }} {{ number_format($row['amount'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="muted">{{ __('documents.receipt_no_allocations') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
