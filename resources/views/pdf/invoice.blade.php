@php
    // Printed documents default to Bahasa Indonesia with a per-document
    // English override — see App\Models\Invoice::resolveDocumentLanguage()
    // and docs/rebuild/specs/06-documents-portal-reporting/Specs.md.
    app()->setLocale($invoice->resolveDocumentLanguage());

    // App\Enums\InvoiceType::getLabel() stays English-only (Filament
    // tables/forms rely on it) — the printed title uses its own
    // translation keys instead.
    $documentTypeLabel = match ($invoice->type) {
        \App\Enums\InvoiceType::Invoice => __('documents.type_invoice'),
        \App\Enums\InvoiceType::Quote => __('documents.type_quote'),
    };
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $documentTypeLabel }} {{ $invoice->number }}</title>
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
                @if ($invoice->company->email)
                    <div class="muted">{{ $invoice->company->email }}</div>
                @endif
                @if ($invoice->company->tax_number)
                    <div class="muted">{{ __('documents.tax_id') }}: {{ $invoice->company->tax_number }}</div>
                @endif
            </td>
            <td width="50%">
                <div class="doc-title">{{ strtoupper($documentTypeLabel) }}</div>
                <div class="doc-meta">{{ $invoice->number }}</div>
                @if ($invoice->invoice_date)
                    <div class="doc-meta">{{ __('documents.date') }}: {{ $invoice->invoice_date->toFormattedDateString() }}</div>
                @endif
                @if ($invoice->due_date)
                    <div class="doc-meta">{{ __('documents.due') }}: {{ $invoice->due_date->toFormattedDateString() }}</div>
                @endif
                @if ($invoice->po_number)
                    <div class="doc-meta">{{ __('documents.po') }}: {{ $invoice->po_number }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div>
        <h4 class="muted" style="margin-bottom: 2px; text-transform: uppercase; font-size: 11px;">{{ __('documents.billed_to') }}</h4>
        <div style="font-weight: bold;">{{ $invoice->client->name }}</div>
        @if ($invoice->client->address_line_1)
            <div class="muted">{{ $invoice->client->address_line_1 }}</div>
        @endif
        @if ($invoice->client->email)
            <div class="muted">{{ $invoice->client->email }}</div>
        @endif
        @if ($invoice->client->tax_number)
            <div class="muted">{{ __('documents.tax_id') }}: {{ $invoice->client->tax_number }}</div>
        @endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('documents.item') }}</th>
                <th class="text-right">{{ __('documents.qty') }}</th>
                <th class="text-right">{{ __('documents.unit_price') }}</th>
                <th class="text-right">{{ __('documents.total_column') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>
                        <div style="font-weight: bold;">{{ $item->title }}</div>
                        @if ($item->description)
                            <div class="muted">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td class="text-right">{{ rtrim(rtrim($item->quantity, '0'), '.') ?: '0' }}</td>
                    <td class="text-right">{{ number_format($item->unit_cost, 2) }}</td>
                    <td class="text-right">{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ __('documents.subtotal') }}</td>
            <td class="text-right">{{ $invoice->currency_code }} {{ number_format($invoice->subtotal, 2) }}</td>
        </tr>
        @if ($invoice->discount > 0)
            <tr>
                <td>{{ __('documents.discount') }}</td>
                <td class="text-right">
                    -{{ $invoice->discount_is_percentage ? $invoice->discount.'%' : number_format($invoice->discount, 2) }}
                </td>
            </tr>
        @endif
        @if ($invoice->tax_total > 0)
            <tr>
                <td>{{ __('documents.tax') }}</td>
                <td class="text-right">{{ $invoice->currency_code }} {{ number_format($invoice->tax_total, 2) }}</td>
            </tr>
        @endif
        <tr class="total">
            <td>{{ __('documents.total') }}</td>
            <td class="text-right">{{ $invoice->currency_code }} {{ number_format($invoice->total, 2) }}</td>
        </tr>
        @if ($invoice->balance != $invoice->total)
            <tr>
                <td>{{ __('documents.balance_due') }}</td>
                <td class="text-right">{{ $invoice->currency_code }} {{ number_format($invoice->balance, 2) }}</td>
            </tr>
        @endif
    </table>

    {{-- public_notes/terms/footer are sanitized HTML from <x-editor> (server-side
         via App\Support\Html\RichTextSanitizer on save, plus the editor's own
         client-side sanitizer) — rendered raw so the formatting the user typed
         (bold, lists, links) survives into the printed PDF. --}}
    @if ($invoice->public_notes)
        <div class="notes">
            <h4>{{ __('documents.notes') }}</h4>
            <div>{!! $invoice->public_notes !!}</div>
        </div>
    @endif

    @if ($invoice->terms)
        <div class="notes">
            <h4>{{ __('documents.terms') }}</h4>
            <div>{!! $invoice->terms !!}</div>
        </div>
    @endif

    @if ($invoice->footer)
        <div class="notes muted">{!! $invoice->footer !!}</div>
    @endif
</body>
</html>
