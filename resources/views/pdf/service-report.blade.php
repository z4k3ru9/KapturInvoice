@php
    // Printed documents default to Bahasa Indonesia with a per-document
    // English override — see
    // App\Models\ServiceReport::resolveDocumentLanguage() and
    // docs/rebuild/specs/FINALIZED-DECISIONS.md §10. Mirrors
    // resources/views/pdf/delivery-order.blade.php's dompdf-safe plain
    // table layout.
    app()->setLocale($serviceReport->resolveDocumentLanguage());

    $client = $serviceReport->salesOrder->client;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('documents.service_report_title') }} {{ $serviceReport->number }}</title>
    <style>
        @include('pdf.partials.page-footer')
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1f2937; }
        .header { width: 100%; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .company-name { font-size: 18px; font-weight: bold; }
        .doc-title { font-size: 20px; font-weight: bold; text-align: right; }
        .doc-meta { text-align: right; color: #6b7280; }
        .muted { color: #6b7280; }
        .section { margin-top: 20px; }
        .section h4 { margin-bottom: 4px; color: #6b7280; font-size: 11px; text-transform: uppercase; }
        .logo { max-height: 48px; max-width: 220px; margin-bottom: 6px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="50%">
                @if ($logoDataUri = $serviceReport->company->getLogoDataUri())
                    <img class="logo" src="{{ $logoDataUri }}" alt="{{ $serviceReport->company->name }}">
                @endif
                <div class="company-name">{{ $serviceReport->company->name }}</div>
                @if ($serviceReport->company->tax_number)
                    <div class="muted">{{ __('documents.tax_id') }}: {{ $serviceReport->company->tax_number }}</div>
                @endif
            </td>
            <td width="50%">
                <div class="doc-title">{{ strtoupper(__('documents.service_report_title')) }}</div>
                <div class="doc-meta">{{ $serviceReport->number }}</div>
                @if ($serviceReport->service_date)
                    <div class="doc-meta">{{ __('documents.date') }}: {{ $serviceReport->service_date->toFormattedDateString() }}</div>
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

    <div class="section">
        <h4>{{ __('documents.service_report_technician') }}</h4>
        <div>{{ $serviceReport->technician?->name ?? $serviceReport->external_technician_name ?? '-' }}</div>
    </div>

    <div class="section">
        <h4>{{ __('documents.service_report_problem_reported') }}</h4>
        <div>{{ $serviceReport->problem_reported ?: '-' }}</div>
    </div>

    <div class="section">
        <h4>{{ __('documents.service_report_diagnosis') }}</h4>
        <div>{{ $serviceReport->diagnosis ?: '-' }}</div>
    </div>

    <div class="section">
        <h4>{{ __('documents.service_report_action_taken') }}</h4>
        <div>{{ $serviceReport->action_taken ?: '-' }}</div>
    </div>

    @if ($serviceReport->parts_used)
        <div class="section">
            <h4>{{ __('documents.service_report_parts_used') }}</h4>
            <div>{{ $serviceReport->parts_used }}</div>
        </div>
    @endif

    <div class="section">
        <h4>{{ __('documents.service_report_result') }}</h4>
        <div>{{ $serviceReport->result?->getLabel() ?? '-' }}</div>
    </div>

    @if ($serviceReport->result?->value === 'follow_up_required' && $serviceReport->follow_up_notes)
        <div class="section">
            <h4>{{ __('documents.service_report_follow_up_notes') }}</h4>
            <div>{{ $serviceReport->follow_up_notes }}</div>
        </div>
    @endif

    <div class="section">
        <h4>{{ __('documents.service_report_customer_acknowledgement') }}</h4>
        <div>{{ $serviceReport->customer_acknowledgement_name ?: '-' }}</div>
    </div>
</body>
</html>
