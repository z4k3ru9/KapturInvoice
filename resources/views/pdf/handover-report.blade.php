@php
    // Printed documents default to Bahasa Indonesia with a per-document
    // English override — see
    // App\Models\HandoverReport::resolveDocumentLanguage() and
    // docs/rebuild/specs/06b-ux-browser-soa/Specs.md "Required launch
    // document coverage." Mirrors resources/views/pdf/invoice.blade.php's
    // dompdf-safe plain table layout.
    app()->setLocale($handoverReport->resolveDocumentLanguage());

    $client = $handoverReport->salesOrder->client;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('documents.handover_report_title') }} {{ $handoverReport->number }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1f2937; }
        .header { width: 100%; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .company-name { font-size: 18px; font-weight: bold; }
        .doc-title { font-size: 20px; font-weight: bold; text-align: right; }
        .doc-meta { text-align: right; color: #6b7280; }
        .muted { color: #6b7280; }
        .notes { margin-top: 24px; }
        .notes h4 { margin-bottom: 4px; color: #6b7280; font-size: 11px; text-transform: uppercase; }
        .logo { max-height: 48px; max-width: 220px; margin-bottom: 6px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="50%">
                @if ($logoDataUri = $handoverReport->company->getLogoDataUri())
                    <img class="logo" src="{{ $logoDataUri }}" alt="{{ $handoverReport->company->name }}">
                @endif
                <div class="company-name">{{ $handoverReport->company->name }}</div>
                @if ($handoverReport->company->tax_number)
                    <div class="muted">{{ __('documents.tax_id') }}: {{ $handoverReport->company->tax_number }}</div>
                @endif
            </td>
            <td width="50%">
                <div class="doc-title">{{ strtoupper(__('documents.handover_report_title')) }}</div>
                <div class="doc-meta">{{ $handoverReport->number }}</div>
                @if ($handoverReport->handover_date)
                    <div class="doc-meta">{{ __('documents.date') }}: {{ $handoverReport->handover_date->toFormattedDateString() }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div>
        <h4 class="muted" style="margin-bottom: 2px; text-transform: uppercase; font-size: 11px;">{{ __('documents.issued_to') }}</h4>
        <div style="font-weight: bold;">{{ $client->name }}</div>
        @if ($client->address_line_1)
            <div class="muted">{{ $client->address_line_1 }}</div>
        @endif
        @if ($client->email)
            <div class="muted">{{ $client->email }}</div>
        @endif
    </div>

    @if ($handoverReport->is_override)
        <div class="notes">
            <h4>{{ __('documents.handover_report_override') }}</h4>
            <div>{{ $handoverReport->override_reason }}</div>
        </div>
    @endif

    @if ($handoverReport->notes)
        <div class="notes">
            <h4>{{ __('documents.notes') }}</h4>
            <div>{{ $handoverReport->notes }}</div>
        </div>
    @endif
</body>
</html>
