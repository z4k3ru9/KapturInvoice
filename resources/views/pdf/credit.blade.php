@php
    // Printed documents default to Bahasa Indonesia with a per-document
    // English override — see App\Models\Credit::resolveDocumentLanguage()
    // and docs/rebuild/specs/06-documents-portal-reporting/Specs.md.
    app()->setLocale($credit->resolveDocumentLanguage());
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('documents.type_credit') }} {{ $credit->number }}</title>
    <style>
        @include('pdf.partials.page-footer')
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1f2937; }
        .header { width: 100%; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .company-name { font-size: 18px; font-weight: bold; }
        .doc-title { font-size: 20px; font-weight: bold; text-align: right; }
        .doc-meta { text-align: right; color: #6b7280; }
        .muted { color: #6b7280; }
        table.totals { width: 260px; margin-left: auto; margin-top: 24px; }
        table.totals td { padding: 3px 4px; }
        table.totals tr.total td { font-weight: bold; font-size: 14px; border-top: 2px solid #1f2937; }
        .text-right { text-align: right; }
        .notes { margin-top: 24px; }
        .notes h4 { margin-bottom: 4px; color: #6b7280; font-size: 11px; text-transform: uppercase; }
        .logo { max-height: 48px; max-width: 220px; margin-bottom: 6px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="50%">
                @if ($logoDataUri = $credit->company->getLogoDataUri())
                    <img class="logo" src="{{ $logoDataUri }}" alt="{{ $credit->company->name }}">
                @endif
                <div class="company-name">{{ $credit->company->name }}</div>
                @if ($credit->company->email)
                    <div class="muted">{{ $credit->company->email }}</div>
                @endif
                @if ($credit->company->tax_number)
                    <div class="muted">{{ __('documents.tax_id') }}: {{ $credit->company->tax_number }}</div>
                @endif
            </td>
            <td width="50%">
                <div class="doc-title">{{ strtoupper(__('documents.type_credit')) }}</div>
                <div class="doc-meta">{{ $credit->number }}</div>
                @if ($credit->credit_date)
                    <div class="doc-meta">{{ __('documents.date') }}: {{ $credit->credit_date->toFormattedDateString() }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div>
        <h4 class="muted" style="margin-bottom: 2px; text-transform: uppercase; font-size: 11px;">{{ __('documents.issued_to') }}</h4>
        <div style="font-weight: bold;">{{ $credit->client?->name ?? '—' }}</div>
        @if ($credit->client?->email)
            <div class="muted">{{ $credit->client->email }}</div>
        @endif
        @if ($credit->client?->tax_number)
            <div class="muted">{{ __('documents.tax_id') }}: {{ $credit->client->tax_number }}</div>
        @endif
    </div>

    <table class="totals">
        <tr class="total">
            <td>{{ __('documents.amount') }}</td>
            <td class="text-right">{{ number_format($credit->amount, 2) }}</td>
        </tr>
        @if ($credit->balance != $credit->amount)
            <tr>
                <td>{{ __('documents.remaining_balance') }}</td>
                <td class="text-right">{{ number_format($credit->balance, 2) }}</td>
            </tr>
        @endif
    </table>

    @if ($credit->public_notes)
        <div class="notes">
            <h4>{{ __('documents.notes') }}</h4>
            <div>{{ $credit->public_notes }}</div>
        </div>
    @endif
</body>
</html>
