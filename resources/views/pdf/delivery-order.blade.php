@php
    // Printed documents default to Bahasa Indonesia with a per-document
    // English override — see
    // App\Models\DeliveryOrder::resolveDocumentLanguage() and
    // docs/rebuild/specs/06b-ux-browser-soa/Specs.md "Required launch
    // document coverage." Mirrors resources/views/pdf/invoice.blade.php's
    // dompdf-safe plain table layout.
    app()->setLocale($deliveryOrder->resolveDocumentLanguage());

    $client = $deliveryOrder->salesOrder->client;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('documents.delivery_order_title') }} {{ $deliveryOrder->number }}</title>
    <style>
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
        .notes { margin-top: 24px; }
        .notes h4 { margin-bottom: 4px; color: #6b7280; font-size: 11px; text-transform: uppercase; }
        .logo { max-height: 48px; max-width: 220px; margin-bottom: 6px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="50%">
                @if ($logoDataUri = $deliveryOrder->company->getLogoDataUri())
                    <img class="logo" src="{{ $logoDataUri }}" alt="{{ $deliveryOrder->company->name }}">
                @endif
                <div class="company-name">{{ $deliveryOrder->company->name }}</div>
                @if ($deliveryOrder->company->tax_number)
                    <div class="muted">{{ __('documents.tax_id') }}: {{ $deliveryOrder->company->tax_number }}</div>
                @endif
            </td>
            <td width="50%">
                <div class="doc-title">{{ strtoupper(__('documents.delivery_order_title')) }}</div>
                <div class="doc-meta">{{ $deliveryOrder->number }}</div>
                @if ($deliveryOrder->delivery_date)
                    <div class="doc-meta">{{ __('documents.date') }}: {{ $deliveryOrder->delivery_date->toFormattedDateString() }}</div>
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

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('documents.delivery_order_item_delivered') }}</th>
                <th class="text-right">{{ __('documents.qty') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($deliveryOrder->items as $item)
                <tr>
                    <td>{{ $item->description ?? $item->salesOrderItem?->title }}</td>
                    <td class="text-right">{{ rtrim(rtrim($item->quantity_delivered, '0'), '.') ?: '0' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($deliveryOrder->notes)
        <div class="notes">
            <h4>{{ __('documents.notes') }}</h4>
            <div>{{ $deliveryOrder->notes }}</div>
        </div>
    @endif
</body>
</html>
