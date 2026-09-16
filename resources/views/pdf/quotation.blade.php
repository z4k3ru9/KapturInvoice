@php
    // Printed documents default to Bahasa Indonesia with a per-document
    // English override — see App\Models\Quotation::resolveDocumentLanguage()
    // and docs/rebuild/specs/06b-ux-browser-soa/Specs.md "Required launch
    // document coverage".
    app()->setLocale($quotation->resolveDocumentLanguage());

    // "Do not invent a customer-issued PO. The Customer Order Confirmation
    // remains an internal record when the customer accepts without
    // supplying a PO." — this quotation prints as a Customer Order
    // Confirmation only once accepted with a system-generated PO number;
    // otherwise (draft/sent/accepted-with-real-PO) it prints as a plain
    // Quotation. `documents.type_coc`/`documents.type_quotation` are new
    // keys — see App\Models\Quotation's docblock; do NOT reuse
    // `documents.type_quote`, which is the legacy Invoice/type=quote label.
    $isCustomerOrderConfirmation = $quotation->customer_po_is_system_generated
        && $quotation->status === \App\Enums\QuotationStatus::Accepted;

    $documentTypeLabel = $isCustomerOrderConfirmation
        ? __('documents.type_coc')
        : __('documents.type_quotation');

    // Nominal discount amount, derived from already-persisted totals rather
    // than recomputed here (App\Services\QuotationTotalsCalculator sets
    // total = subtotal - discount exactly, no separate rounding step, so
    // this subtraction recovers the identical figure it used) — never a new
    // calculation, per CLAUDE.md's "printed financial document" guard.
    $discountNominal = $quotation->discount > 0
        ? round((float) $quotation->subtotal - (float) $quotation->total, 2)
        : 0.0;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $documentTypeLabel }} {{ $quotation->number }}</title>
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
        .doc-meta.emphasis { color: #1f2937; font-weight: bold; }
        .muted { color: #6b7280; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.items th { text-align: left; border-bottom: 2px solid #1f2937; padding: 6px 4px; font-size: 11px; text-transform: uppercase; color: #6b7280; }
        table.items td { padding: 6px 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.items tbody tr:nth-child(even) { background-color: #f9fafb; }
        .text-right { text-align: right; }
        .item-picture { width: 40px; height: 40px; }
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
                @if ($logoDataUri = $quotation->company->getLogoDataUri())
                    <img class="logo" src="{{ $logoDataUri }}" alt="{{ $quotation->company->name }}">
                @endif
                <div class="company-name">{{ $quotation->company->name }}</div>
                @if ($quotation->company->address_line_1)
                    <div class="muted">{{ $quotation->company->address_line_1 }}</div>
                @endif
                @if ($quotation->company->city || $quotation->company->postal_code)
                    <div class="muted">{{ collect([$quotation->company->city, $quotation->company->state, $quotation->company->postal_code])->filter()->implode(', ') }}</div>
                @endif
                @if ($quotation->company->email)
                    <div class="muted">{{ $quotation->company->email }}</div>
                @endif
                @if ($quotation->company->tax_number)
                    <div class="muted">{{ __('documents.tax_id') }}: {{ $quotation->company->tax_number }}</div>
                @endif
            </td>
            <td width="50%">
                <div class="doc-title">{{ strtoupper($documentTypeLabel) }}</div>
                <div class="doc-meta">{{ $quotation->number }}</div>
                @if ($quotation->quotation_date)
                    <div class="doc-meta">{{ __('documents.date') }}: {{ $quotation->quotation_date->toFormattedDateString() }}</div>
                @endif
                @if ($quotation->valid_until)
                    <div class="doc-meta">{{ __('documents.quotation_valid_until') }}: {{ $quotation->valid_until->toFormattedDateString() }}</div>
                @endif
                @if ($isCustomerOrderConfirmation)
                    {{-- The system-generated COC number is this same
                         quotation's `customer_po_number` — shown prominently,
                         never presented as a customer-issued PO. --}}
                    <div class="doc-meta emphasis">{{ __('documents.coc_no') }}: {{ $quotation->customer_po_number }}</div>
                @elseif ($quotation->customer_po_number)
                    <div class="doc-meta">{{ __('documents.po') }}: {{ $quotation->customer_po_number }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div>
        <h4 class="muted" style="margin-bottom: 2px; text-transform: uppercase; font-size: 11px;">{{ __('documents.billed_to') }}</h4>
        <div style="font-weight: bold;">{{ $quotation->client->name }}</div>
        @if ($quotation->client->address_line_1)
            <div class="muted">{{ $quotation->client->address_line_1 }}</div>
        @endif
        @if ($quotation->client->email)
            <div class="muted">{{ $quotation->client->email }}</div>
        @endif
        @if ($quotation->client->tax_number)
            <div class="muted">{{ __('documents.tax_id') }}: {{ $quotation->client->tax_number }}</div>
        @endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th></th>
                <th>{{ __('documents.item') }}</th>
                <th class="text-right">{{ __('documents.qty') }}</th>
                <th class="text-left">{{ __('documents.unit') }}</th>
                <th class="text-right">{{ __('documents.unit_price') }}</th>
                <th class="text-right">{{ __('documents.total_column') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->items as $item)
                <tr>
                    <td>
                        @if ($item->product && ($imageDataUri = $item->product->getImageDataUri()))
                            <img class="item-picture" src="{{ $imageDataUri }}" alt="{{ $item->title }}">
                        @endif
                    </td>
                    <td>
                        <div style="font-weight: bold;">{{ $item->title }}</div>
                        @if ($item->description)
                            <div class="muted">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td class="text-right">{{ rtrim(rtrim($item->quantity, '0'), '.') ?: '0' }}</td>
                    <td>{{ $item->unit?->getAbbreviation() ?? '-' }}</td>
                    <td class="text-right">{{ number_format($item->unit_cost, 2) }}</td>
                    <td class="text-right">{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ __('documents.subtotal') }}</td>
            <td class="text-right">{{ $quotation->company->currency_code }} {{ number_format($quotation->subtotal, 2) }}</td>
        </tr>
        @if ($quotation->discount > 0)
            <tr>
                <td>{{ __('documents.discount') }}</td>
                <td class="text-right">
                    @if ($quotation->discount_is_percentage)
                        -{{ $quotation->discount }}% (-{{ $quotation->company->currency_code }} {{ number_format($discountNominal, 2) }})
                    @else
                        -{{ number_format($quotation->discount, 2) }}
                    @endif
                </td>
            </tr>
        @endif
        <tr class="total">
            <td>{{ __('documents.total') }}</td>
            <td class="text-right">{{ $quotation->company->currency_code }} {{ number_format($quotation->total, 2) }}</td>
        </tr>
    </table>

    {{-- notes/terms are sanitized HTML from <x-editor> — see resources/views/pdf/invoice.blade.php's note. --}}
    @if ($quotation->notes)
        <div class="notes">
            <h4>{{ __('documents.notes') }}</h4>
            <div>{!! $quotation->notes !!}</div>
        </div>
    @endif

    @if ($quotation->terms)
        <div class="notes">
            <h4>{{ __('documents.terms') }}</h4>
            <div>{!! $quotation->terms !!}</div>
        </div>
    @endif
</body>
</html>
