@php
    // Printed documents default to Bahasa Indonesia with a per-document
    // English override — see App\Models\TaxRecap::resolveDocumentLanguage()
    // and docs/rebuild/specs/06b-ux-browser-soa/Specs.md.
    app()->setLocale($taxRecap->resolveDocumentLanguage());

    $invoice = $taxRecap->invoice;
    $snapshot = $invoice->taxSnapshot;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('documents.tax_recap_title') }} {{ $taxRecap->number }}</title>
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
        table.fields { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.fields td { padding: 6px 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.fields td.label { width: 40%; color: #6b7280; text-transform: uppercase; font-size: 11px; }
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
                @if ($logoDataUri = $invoice->company->getLogoDataUri())
                    <img class="logo" src="{{ $logoDataUri }}" alt="{{ $invoice->company->name }}">
                @endif
                <div class="company-name">{{ $invoice->company->name }}</div>
                @if ($invoice->company->address_line_1)
                    <div class="muted">{{ $invoice->company->address_line_1 }}</div>
                @endif
                @if ($invoice->company->city || $invoice->company->postal_code)
                    <div class="muted">{{ collect([$invoice->company->city, $invoice->company->state, $invoice->company->postal_code])->filter()->implode(', ') }}</div>
                @endif
                @if ($invoice->company->tax_number)
                    <div class="muted">{{ __('documents.tax_id') }}: {{ $invoice->company->tax_number }}</div>
                @endif
            </td>
            <td width="50%">
                <div class="doc-title">{{ strtoupper(__('documents.tax_recap_title')) }}</div>
                <div class="doc-meta">{{ $taxRecap->number }}</div>
                @if ($taxRecap->created_at)
                    <div class="doc-meta">{{ __('documents.date') }}: {{ $taxRecap->created_at->toFormattedDateString() }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="fields">
        <tr>
            <td class="label">{{ __('documents.type_invoice') }}</td>
            <td>{{ $invoice->number }} @if ($invoice->invoice_date) &mdash; {{ $invoice->invoice_date->toFormattedDateString() }} @endif</td>
        </tr>
        <tr>
            <td class="label">{{ __('documents.tax_recap_reporting_period') }}</td>
            <td>{{ $taxRecap->reporting_period }}</td>
        </tr>
        @if ($taxRecap->external_reference)
            <tr>
                <td class="label">{{ __('documents.tax_recap_external_reference') }}</td>
                <td>{{ $taxRecap->external_reference }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">{{ __('documents.tax_recap_manual_entry_status') }}</td>
            <td>{{ $taxRecap->manual_entry_status }}</td>
        </tr>
        @if ($taxRecap->filing_date)
            <tr>
                <td class="label">{{ __('documents.tax_recap_filing_date') }}</td>
                <td>{{ $taxRecap->filing_date->toFormattedDateString() }}</td>
            </tr>
        @endif
        @if ($taxRecap->attachment_reference)
            <tr>
                <td class="label">{{ __('documents.tax_recap_attachment_reference') }}</td>
                <td>{{ $taxRecap->attachment_reference }}</td>
            </tr>
        @endif
    </table>

    @if ($snapshot)
        <table class="totals">
            <tr>
                <td>{{ __('documents.tax_recap_taxable_base') }}</td>
                <td class="text-right">{{ $invoice->currency_code }} {{ number_format($snapshot->taxable_base_total, 2) }}</td>
            </tr>
            <tr class="total">
                <td>{{ __('documents.tax') }}</td>
                <td class="text-right">{{ $invoice->currency_code }} {{ number_format($snapshot->tax_total, 2) }}</td>
            </tr>
        </table>
    @endif

    @if ($taxRecap->notes)
        <div class="notes">
            <h4>{{ __('documents.notes') }}</h4>
            <div>{{ $taxRecap->notes }}</div>
        </div>
    @endif
</body>
</html>
