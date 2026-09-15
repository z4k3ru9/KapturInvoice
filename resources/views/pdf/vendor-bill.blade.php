@php
    // Printed documents default to Bahasa Indonesia with a per-document
    // English override — see App\Models\VendorBill::resolveDocumentLanguage()
    // and docs/rebuild/specs/06b-ux-browser-soa/Specs.md "Required launch
    // document coverage." Mirrors resources/views/pdf/invoice.blade.php's
    // dompdf-safe plain table layout.
    app()->setLocale($vendorBill->resolveDocumentLanguage());
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('documents.vendor_bill_title') }} {{ $vendorBill->number }}</title>
    <style>
        @include('pdf.partials.page-footer')
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
                @if ($logoDataUri = $vendorBill->company->getLogoDataUri())
                    <img class="logo" src="{{ $logoDataUri }}" alt="{{ $vendorBill->company->name }}">
                @endif
                <div class="company-name">{{ $vendorBill->company->name }}</div>
                @if ($vendorBill->company->address_line_1)
                    <div class="muted">{{ $vendorBill->company->address_line_1 }}</div>
                @endif
                @if ($vendorBill->company->tax_number)
                    <div class="muted">{{ __('documents.tax_id') }}: {{ $vendorBill->company->tax_number }}</div>
                @endif
            </td>
            <td width="50%">
                <div class="doc-title">{{ strtoupper(__('documents.vendor_bill_title')) }}</div>
                <div class="doc-meta">{{ $vendorBill->number }}</div>
                @if ($vendorBill->bill_date)
                    <div class="doc-meta">{{ __('documents.date') }}: {{ $vendorBill->bill_date->toFormattedDateString() }}</div>
                @endif
                @if ($vendorBill->due_date)
                    <div class="doc-meta">{{ __('documents.due') }}: {{ $vendorBill->due_date->toFormattedDateString() }}</div>
                @endif
                @if ($vendorBill->vendorPurchaseOrder?->number)
                    <div class="doc-meta">{{ __('documents.vendor_bill_po_reference') }}: {{ $vendorBill->vendorPurchaseOrder->number }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- This document is FROM the vendor TO the company — the "billed to"
         block becomes the vendor's block instead of a client's. --}}
    <div>
        <h4 class="muted" style="margin-bottom: 2px; text-transform: uppercase; font-size: 11px;">{{ __('documents.vendor_po_issued_to_vendor') }}</h4>
        <div style="font-weight: bold;">{{ $vendorBill->vendor->name }}</div>
        @if ($vendorBill->vendor->address_line_1)
            <div class="muted">{{ $vendorBill->vendor->address_line_1 }}</div>
        @endif
        @if ($vendorBill->vendor->email)
            <div class="muted">{{ $vendorBill->vendor->email }}</div>
        @endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('documents.item') }}</th>
                <th class="text-right">{{ __('documents.qty') }}</th>
                <th class="text-right">{{ __('documents.vendor_bill_net') }}</th>
                <th class="text-right">{{ __('documents.tax') }}</th>
                <th class="text-right">{{ __('documents.total_column') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($vendorBill->items as $item)
                <tr>
                    <td>
                        <div style="font-weight: bold;">{{ $item->title }}</div>
                        @if ($item->description)
                            <div class="muted">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td class="text-right">{{ rtrim(rtrim($item->quantity, '0'), '.') ?: '0' }}</td>
                    <td class="text-right">{{ number_format($item->net_amount, 2) }}</td>
                    <td class="text-right">{{ number_format($item->tax_amount, 2) }}</td>
                    <td class="text-right">{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr class="total">
            <td>{{ __('documents.total') }}</td>
            <td class="text-right">{{ $vendorBill->company->currency_code }} {{ number_format($vendorBill->total, 2) }}</td>
        </tr>
        <tr>
            <td>{{ __('documents.vendor_bill_amount_paid') }}</td>
            <td class="text-right">{{ $vendorBill->company->currency_code }} {{ number_format($vendorBill->amount_paid, 2) }}</td>
        </tr>
        <tr>
            <td>{{ __('documents.balance_due') }}</td>
            <td class="text-right">{{ $vendorBill->company->currency_code }} {{ number_format($vendorBill->balance, 2) }}</td>
        </tr>
        <tr>
            <td>{{ __('documents.vendor_bill_status') }}</td>
            <td class="text-right">{{ $vendorBill->status->getLabel() }}</td>
        </tr>
    </table>

    {{-- notes is sanitized HTML from <x-editor> — see resources/views/pdf/invoice.blade.php's note. --}}
    @if ($vendorBill->notes)
        <div class="notes">
            <h4>{{ __('documents.notes') }}</h4>
            <div>{!! $vendorBill->notes !!}</div>
        </div>
    @endif
</body>
</html>
