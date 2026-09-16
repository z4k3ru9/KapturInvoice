@php
    // Printed documents default to Bahasa Indonesia with a per-document
    // English override — see App\Models\SalesOrder::resolveDocumentLanguage()
    // and docs/rebuild/specs/06b-ux-browser-soa/Specs.md "Required launch
    // document coverage".
    app()->setLocale($salesOrder->resolveDocumentLanguage());

    // `source_snapshot` is the frozen copy of the accepted quotation's
    // header fields captured at job-creation time (see
    // App\Actions\Sales\CreateSalesOrderFromQuotation) — SalesOrder itself
    // has no `subtotal`/`discount`/`pricing_mode` columns of its own, only
    // `approved_value` (which already reflects any approved Job Variation
    // since — never re-derived from the snapshot). Line items come from the
    // real, live `sales_order_items` relation, which was copied (not just
    // snapshotted) at the same time.
    $snapshot = $salesOrder->source_snapshot ?? [];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('documents.type_sales_order') }} {{ $salesOrder->number }}</title>
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
                @if ($logoDataUri = $salesOrder->company->getLogoDataUri())
                    <img class="logo" src="{{ $logoDataUri }}" alt="{{ $salesOrder->company->name }}">
                @endif
                <div class="company-name">{{ $salesOrder->company->name }}</div>
                @if ($salesOrder->company->address_line_1)
                    <div class="muted">{{ $salesOrder->company->address_line_1 }}</div>
                @endif
                @if ($salesOrder->company->email)
                    <div class="muted">{{ $salesOrder->company->email }}</div>
                @endif
                @if ($salesOrder->company->tax_number)
                    <div class="muted">{{ __('documents.tax_id') }}: {{ $salesOrder->company->tax_number }}</div>
                @endif
            </td>
            <td width="50%">
                <div class="doc-title">{{ strtoupper(__('documents.type_sales_order')) }}</div>
                <div class="doc-meta">{{ $salesOrder->number }}</div>
                <div class="doc-meta">{{ __('documents.sales_order_status') }}: {{ $salesOrder->status->getLabel() }}</div>
                @if ($salesOrder->quotation?->number)
                    <div class="doc-meta">{{ __('documents.sales_order_quotation_ref') }}: {{ $salesOrder->quotation->number }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div>
        <h4 class="muted" style="margin-bottom: 2px; text-transform: uppercase; font-size: 11px;">{{ __('documents.billed_to') }}</h4>
        <div style="font-weight: bold;">{{ $salesOrder->client->name }}</div>
        @if ($salesOrder->client->address_line_1)
            <div class="muted">{{ $salesOrder->client->address_line_1 }}</div>
        @endif
        @if ($salesOrder->client->email)
            <div class="muted">{{ $salesOrder->client->email }}</div>
        @endif
        @if ($salesOrder->client->tax_number)
            <div class="muted">{{ __('documents.tax_id') }}: {{ $salesOrder->client->tax_number }}</div>
        @endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('documents.item') }}</th>
                <th class="text-right">{{ __('documents.qty') }}</th>
                <th>{{ __('documents.unit') }}</th>
                <th class="text-right">{{ __('documents.unit_price') }}</th>
                <th class="text-right">{{ __('documents.total_column') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($salesOrder->items as $item)
                <tr>
                    <td>
                        <div style="font-weight: bold;">{{ $item->title }}</div>
                        @if ($item->description)
                            <div class="muted">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td class="text-right">{{ rtrim(rtrim($item->quantity, '0'), '.') ?: '0' }}</td>
                    <td>{{ $item->unit?->getAbbreviation() ?? '—' }}</td>
                    <td class="text-right">{{ number_format($item->unit_cost, 2) }}</td>
                    <td class="text-right">{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @if (isset($snapshot['subtotal']))
            <tr>
                <td>{{ __('documents.subtotal') }}</td>
                <td class="text-right">{{ $salesOrder->company->currency_code }} {{ number_format((float) $snapshot['subtotal'], 2) }}</td>
            </tr>
        @endif
        @if (! empty($snapshot['discount']) && (float) $snapshot['discount'] > 0)
            <tr>
                <td>{{ __('documents.discount') }}</td>
                <td class="text-right">
                    -{{ ! empty($snapshot['discount_is_percentage']) ? $snapshot['discount'].'%' : number_format((float) $snapshot['discount'], 2) }}
                </td>
            </tr>
        @endif
        <tr class="total">
            <td>{{ __('documents.sales_order_approved_value') }}</td>
            <td class="text-right">{{ $salesOrder->company->currency_code }} {{ number_format($salesOrder->approved_value, 2) }}</td>
        </tr>
    </table>
</body>
</html>
