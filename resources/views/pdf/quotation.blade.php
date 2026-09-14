<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $quotation->number }}</title>
    <style>
        {{-- dompdf has limited CSS support (no flexbox/grid) — plain
             block/table layout only, same as resources/views/pdf/invoice.blade.php. --}}
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1f2937; }
        .header { width: 100%; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .company-name { font-size: 18px; font-weight: bold; }
        .doc-title { font-size: 20px; font-weight: bold; text-align: right; }
        .doc-meta { text-align: right; color: #6b7280; }
        .muted { color: #6b7280; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.items th { text-align: left; border-bottom: 2px solid #1f2937; padding: 6px 4px; font-size: 11px; text-transform: uppercase; color: #6b7280; }
        table.items td { padding: 6px 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
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
                    <div class="muted">Tax ID: {{ $quotation->company->tax_number }}</div>
                @endif
            </td>
            <td width="50%">
                <div class="doc-title">QUOTATION</div>
                <div class="doc-meta">{{ $quotation->number }}</div>
                @if ($quotation->quotation_date)
                    <div class="doc-meta">Date: {{ $quotation->quotation_date->toFormattedDateString() }}</div>
                @endif
                @if ($quotation->valid_until)
                    <div class="doc-meta">Valid until: {{ $quotation->valid_until->toFormattedDateString() }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div>
        <h4 class="muted" style="margin-bottom: 2px; text-transform: uppercase; font-size: 11px;">Quoted to</h4>
        <div style="font-weight: bold;">{{ $quotation->client->name }}</div>
        @if ($quotation->client->address_line_1)
            <div class="muted">{{ $quotation->client->address_line_1 }}</div>
        @endif
        @if ($quotation->client->email)
            <div class="muted">{{ $quotation->client->email }}</div>
        @endif
        @if ($quotation->client->tax_number)
            <div class="muted">Tax ID: {{ $quotation->client->tax_number }}</div>
        @endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th></th>
                <th>Item</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit price</th>
                <th class="text-right">Total</th>
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
                    <td class="text-right">{{ number_format($item->unit_cost, 2) }}</td>
                    <td class="text-right">{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="text-right">{{ $quotation->company->currency_code }} {{ number_format($quotation->subtotal, 2) }}</td>
        </tr>
        @if ($quotation->discount > 0)
            <tr>
                <td>Discount</td>
                <td class="text-right">
                    -{{ $quotation->discount_is_percentage ? $quotation->discount.'%' : number_format($quotation->discount, 2) }}
                </td>
            </tr>
        @endif
        <tr class="total">
            <td>Total</td>
            <td class="text-right">{{ $quotation->company->currency_code }} {{ number_format($quotation->total, 2) }}</td>
        </tr>
    </table>

    @if ($quotation->terms)
        <div class="notes">
            <h4>Terms</h4>
            <div>{{ $quotation->terms }}</div>
        </div>
    @endif

    @if ($quotation->notes)
        <div class="notes">
            <h4>Notes</h4>
            <div>{{ $quotation->notes }}</div>
        </div>
    @endif
</body>
</html>
