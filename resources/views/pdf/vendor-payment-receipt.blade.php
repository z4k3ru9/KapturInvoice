@php
    // Printed documents default to Bahasa Indonesia with a per-document
    // English override — see
    // App\Models\VendorPaymentReceipt::resolveDocumentLanguage() and
    // docs/rebuild/specs/06b-ux-browser-soa/Specs.md "Required launch
    // document coverage." Mirrors resources/views/pdf/invoice.blade.php's
    // dompdf-safe plain table layout.
    app()->setLocale($vendorPaymentReceipt->resolveDocumentLanguage());

    $vendorPayment = $vendorPaymentReceipt->vendorPayment;
    $vendorBill = $vendorPayment->vendorBill;
    $vendor = $vendorBill->vendor;

    // Render from the frozen `snapshot` captured at issuance
    // (App\Actions\Procurement\IssueVendorPaymentReceipt) — never the
    // vendor payment's own live `amount`, which
    // App\Actions\Procurement\AmendVendorPayment mutates directly. A
    // receipt issued before this snapshot existed falls back to the live
    // data it always rendered from, rather than showing blank values.
    $snapshot = $vendorPaymentReceipt->snapshot;
    $amount = $snapshot['amount'] ?? $vendorPayment->amount;
    $method = $snapshot['method'] ?? $vendorPayment->method;
    $reference = $snapshot['reference'] ?? $vendorPayment->reference;
    $notes = $snapshot['notes'] ?? $vendorPayment->notes;
    $vendorBillNumber = $snapshot['vendor_bill_number'] ?? $vendorBill->number;
    $vendorBillTotal = $snapshot['vendor_bill_total'] ?? $vendorBill->total;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('documents.vendor_payment_receipt_title') }} {{ $vendorPaymentReceipt->number }}</title>
    <style>
        @include('pdf.partials.page-footer')
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1f2937; }
        .header { width: 100%; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .company-name { font-size: 18px; font-weight: bold; }
        .doc-title { font-size: 20px; font-weight: bold; text-align: right; }
        .doc-meta { text-align: right; color: #6b7280; }
        .muted { color: #6b7280; }
        .text-right { text-align: right; }
        table.totals { width: 320px; margin-left: auto; margin-top: 24px; }
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
                @if ($logoDataUri = $vendorPaymentReceipt->company->getLogoDataUri())
                    <img class="logo" src="{{ $logoDataUri }}" alt="{{ $vendorPaymentReceipt->company->name }}">
                @endif
                <div class="company-name">{{ $vendorPaymentReceipt->company->name }}</div>
                @if ($vendorPaymentReceipt->company->tax_number)
                    <div class="muted">{{ __('documents.tax_id') }}: {{ $vendorPaymentReceipt->company->tax_number }}</div>
                @endif
            </td>
            <td width="50%">
                <div class="doc-title">{{ strtoupper(__('documents.vendor_payment_receipt_title')) }}</div>
                <div class="doc-meta">{{ $vendorPaymentReceipt->number }}</div>
                @if ($vendorPaymentReceipt->issued_at)
                    <div class="doc-meta">{{ __('documents.date') }}: {{ $vendorPaymentReceipt->issued_at->toFormattedDateString() }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div>
        <h4 class="muted" style="margin-bottom: 2px; text-transform: uppercase; font-size: 11px;">{{ __('documents.vendor_po_issued_to_vendor') }}</h4>
        <div style="font-weight: bold;">{{ $vendor->name }}</div>
        @if ($vendor->email)
            <div class="muted">{{ $vendor->email }}</div>
        @endif
    </div>

    <table class="totals">
        <tr class="total">
            <td>{{ __('documents.amount') }}</td>
            <td class="text-right">{{ $vendorPaymentReceipt->company->currency_code }} {{ number_format($amount, 2) }}</td>
        </tr>
        <tr>
            <td>{{ __('documents.vendor_payment_receipt_method') }}</td>
            <td class="text-right">{{ $method ?? '-' }}</td>
        </tr>
        <tr>
            <td>{{ __('documents.vendor_payment_receipt_reference') }}</td>
            <td class="text-right">{{ $reference ?? '-' }}</td>
        </tr>
        <tr>
            <td>{{ __('documents.vendor_payment_receipt_applied_to') }}</td>
            <td class="text-right">{{ $vendorBillNumber }} ({{ $vendorPaymentReceipt->company->currency_code }} {{ number_format($vendorBillTotal, 2) }})</td>
        </tr>
    </table>

    @if ($notes)
        <div class="notes">
            <h4>{{ __('documents.notes') }}</h4>
            <div>{{ $notes }}</div>
        </div>
    @endif
</body>
</html>
