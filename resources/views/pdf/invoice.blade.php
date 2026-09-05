<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->type->getLabel() }} {{ $invoice->number }}</title>
    <style>
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
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="50%">
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
            </td>
            <td width="50%">
                <div class="doc-title">{{ strtoupper($invoice->type->getLabel()) }}</div>
                <div class="doc-meta">{{ $invoice->number }}</div>
                @if ($invoice->invoice_date)
                    <div class="doc-meta">Date: {{ $invoice->invoice_date->toFormattedDateString() }}</div>
                @endif
                @if ($invoice->due_date)
                    <div class="doc-meta">Due: {{ $invoice->due_date->toFormattedDateString() }}</div>
                @endif
                @if ($invoice->po_number)
                    <div class="doc-meta">PO: {{ $invoice->po_number }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div>
        <h4 class="muted" style="margin-bottom: 2px; text-transform: uppercase; font-size: 11px;">Billed to</h4>
        <div style="font-weight: bold;">{{ $invoice->client->name }}</div>
        @if ($invoice->client->address_line_1)
            <div class="muted">{{ $invoice->client->address_line_1 }}</div>
        @endif
        @if ($invoice->client->email)
            <div class="muted">{{ $invoice->client->email }}</div>
        @endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit price</th>
                <th class="text-right">Total</th>
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
            <td>Subtotal</td>
            <td class="text-right">{{ $invoice->currency_code }} {{ number_format($invoice->subtotal, 2) }}</td>
        </tr>
        @if ($invoice->discount > 0)
            <tr>
                <td>Discount</td>
                <td class="text-right">
                    -{{ $invoice->discount_is_percentage ? $invoice->discount.'%' : number_format($invoice->discount, 2) }}
                </td>
            </tr>
        @endif
        @if ($invoice->tax_total > 0)
            <tr>
                <td>Tax</td>
                <td class="text-right">{{ $invoice->currency_code }} {{ number_format($invoice->tax_total, 2) }}</td>
            </tr>
        @endif
        <tr class="total">
            <td>Total</td>
            <td class="text-right">{{ $invoice->currency_code }} {{ number_format($invoice->total, 2) }}</td>
        </tr>
        @if ($invoice->balance != $invoice->total)
            <tr>
                <td>Balance due</td>
                <td class="text-right">{{ $invoice->currency_code }} {{ number_format($invoice->balance, 2) }}</td>
            </tr>
        @endif
    </table>

    @if ($invoice->public_notes)
        <div class="notes">
            <h4>Notes</h4>
            <div>{{ $invoice->public_notes }}</div>
        </div>
    @endif

    @if ($invoice->terms)
        <div class="notes">
            <h4>Terms</h4>
            <div>{{ $invoice->terms }}</div>
        </div>
    @endif

    @if ($invoice->footer)
        <div class="notes muted">{{ $invoice->footer }}</div>
    @endif
</body>
</html>
