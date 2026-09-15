@php
    // Printed documents default to Bahasa Indonesia with a per-document
    // English override — see App\Models\StatementOfAccount::resolveDocumentLanguage()
    // and docs/rebuild/specs/06-documents-portal-reporting/Specs.md. The
    // 'soa_*' translation keys used below are the same "constructed,
    // standard-Indonesian-business-terminology best effort" flagged on
    // resources/lang/id/documents.php's own docblock — not yet validated
    // against an approved client glossary.
    app()->setLocale($statementOfAccount->resolveDocumentLanguage());

    $snapshot = $statementOfAccount->snapshot ?? [];
    $openingBalance = (float) ($snapshot['opening_balance'] ?? 0);
    $closingBalance = (float) ($snapshot['closing_balance'] ?? 0);
    $aging = $snapshot['aging'] ?? [];
    $unresolvedExceptionsTotal = (float) ($snapshot['unresolved_exceptions_total'] ?? 0);

    // Combine invoices/credits/payments into one chronological ledger with
    // a running balance — invoices are debits (increase what's owed),
    // confirmed credits and verified payments are credits (decrease it).
    // Void/Amended invoices, unconfirmed credits, and Reversed payments
    // all carry a balance_contribution of 0 (see
    // App\Services\Reports\BuildStatementOfAccount) so they show up with
    // their status label but never move the running balance. Receipts are
    // shown in their own table further down — they document a payment
    // already counted here, so they never get their own ledger line.
    $ledgerRows = collect();

    foreach (($snapshot['invoices'] ?? []) as $row) {
        $ledgerRows->push([
            'date' => $row['document_date'],
            'document' => $row['number'],
            'description' => __('documents.type_invoice').' — '.$row['status_label'],
            'debit' => (float) $row['balance_contribution'],
            'credit' => 0.0,
        ]);
    }

    foreach (($snapshot['credits'] ?? []) as $row) {
        $ledgerRows->push([
            'date' => $row['document_date'],
            'document' => $row['number'],
            'description' => __('documents.type_credit').($row['confirmed'] ? '' : ' — '.__('documents.soa_unresolved')),
            'debit' => 0.0,
            'credit' => (float) $row['balance_contribution'],
        ]);
    }

    foreach (($snapshot['payments'] ?? []) as $row) {
        $ledgerRows->push([
            'date' => $row['verified_at'],
            'document' => $row['reference'] ?: ('#'.$row['id']),
            'description' => __('documents.soa_payment').' — '.$row['status_label'].($row['method'] ? ' ('.$row['method'].')' : ''),
            'debit' => 0.0,
            'credit' => (float) $row['balance_contribution'],
        ]);
    }

    $ledgerRows = $ledgerRows->sortBy('date')->values();

    $runningBalance = $openingBalance;
    $ledgerRows = $ledgerRows->map(function ($row) use (&$runningBalance) {
        $runningBalance = round($runningBalance + $row['debit'] - $row['credit'], 2);
        $row['balance'] = $runningBalance;

        return $row;
    });
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('documents.soa_title') }} {{ $statementOfAccount->number }}</title>
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
        .preview-banner { background: #fef3c7; color: #92400e; padding: 6px 10px; margin-bottom: 12px; font-weight: bold; text-align: center; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.items th { text-align: left; border-bottom: 2px solid #1f2937; padding: 6px 4px; font-size: 11px; text-transform: uppercase; color: #6b7280; }
        table.items td { padding: 6px 4px; border-bottom: 1px solid #e5e7eb; }
        .text-right { text-align: right; }
        table.totals { width: 260px; margin-left: auto; margin-top: 12px; }
        table.totals td { padding: 3px 4px; }
        table.totals tr.total td { font-weight: bold; font-size: 14px; border-top: 2px solid #1f2937; }
        table.aging { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.aging th, table.aging td { text-align: right; padding: 6px 4px; border-bottom: 1px solid #e5e7eb; }
        table.aging th:first-child, table.aging td:first-child { text-align: left; }
        .notes { margin-top: 24px; }
        .notes h4 { margin-bottom: 4px; color: #6b7280; font-size: 11px; text-transform: uppercase; }
        .logo { max-height: 48px; max-width: 220px; margin-bottom: 6px; }
    </style>
</head>
<body>
    @unless ($statementOfAccount->number)
        <div class="preview-banner">{{ __('documents.soa_preview') }}</div>
    @endunless

    <table class="header">
        <tr>
            <td width="50%">
                @if ($logoDataUri = $statementOfAccount->company->getLogoDataUri())
                    <img class="logo" src="{{ $logoDataUri }}" alt="{{ $statementOfAccount->company->name }}">
                @endif
                <div class="company-name">{{ $statementOfAccount->company->name }}</div>
                @if ($statementOfAccount->company->address_line_1)
                    <div class="muted">{{ $statementOfAccount->company->address_line_1 }}</div>
                @endif
                @if ($statementOfAccount->company->email)
                    <div class="muted">{{ $statementOfAccount->company->email }}</div>
                @endif
                @if ($statementOfAccount->company->tax_number)
                    <div class="muted">{{ __('documents.tax_id') }}: {{ $statementOfAccount->company->tax_number }}</div>
                @endif
            </td>
            <td width="50%">
                <div class="doc-title">{{ strtoupper(__('documents.soa_title')) }}</div>
                @if ($statementOfAccount->number)
                    <div class="doc-meta">{{ $statementOfAccount->number }}</div>
                @endif
                <div class="doc-meta">
                    {{ __('documents.soa_period') }}:
                    {{ \Illuminate\Support\Carbon::parse($statementOfAccount->period_start)->toFormattedDateString() }}
                    –
                    {{ \Illuminate\Support\Carbon::parse($statementOfAccount->period_end)->toFormattedDateString() }}
                </div>
                @if ($statementOfAccount->generated_at)
                    <div class="doc-meta">{{ __('documents.soa_generated_at') }}: {{ $statementOfAccount->generated_at->toFormattedDateString() }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div>
        <h4 class="muted" style="margin-bottom: 2px; text-transform: uppercase; font-size: 11px;">{{ __('documents.soa_client') }}</h4>
        <div style="font-weight: bold;">{{ $statementOfAccount->client->name }}</div>
        @if ($statementOfAccount->client->address_line_1)
            <div class="muted">{{ $statementOfAccount->client->address_line_1 }}</div>
        @endif
        @if ($statementOfAccount->client->email)
            <div class="muted">{{ $statementOfAccount->client->email }}</div>
        @endif
        @if ($statementOfAccount->client->tax_number)
            <div class="muted">{{ __('documents.tax_id') }}: {{ $statementOfAccount->client->tax_number }}</div>
        @endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('documents.soa_date') }}</th>
                <th>{{ __('documents.soa_document') }}</th>
                <th>{{ __('documents.soa_description') }}</th>
                <th class="text-right">{{ __('documents.soa_debit') }}</th>
                <th class="text-right">{{ __('documents.soa_credit') }}</th>
                <th class="text-right">{{ __('documents.soa_balance') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="5"><strong>{{ __('documents.soa_opening_balance') }}</strong></td>
                <td class="text-right"><strong>{{ number_format($openingBalance, 2) }}</strong></td>
            </tr>
            @forelse ($ledgerRows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['document'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="text-right">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '' }}</td>
                    <td class="text-right">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '' }}</td>
                    <td class="text-right">{{ number_format($row['balance'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">{{ __('documents.soa_no_activity') }}</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="5"><strong>{{ __('documents.soa_closing_balance') }}</strong></td>
                <td class="text-right"><strong>{{ number_format($closingBalance, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    @if (! empty($snapshot['receipts']))
        <div class="notes">
            <h4>{{ __('documents.soa_receipts') }}</h4>
            <table class="items">
                <thead>
                    <tr>
                        <th>{{ __('documents.soa_verified_date') }}</th>
                        <th>{{ __('documents.soa_receipt_number') }}</th>
                        <th class="text-right">{{ __('documents.amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($snapshot['receipts'] as $receipt)
                        <tr>
                            <td>{{ $receipt['issued_at'] }}</td>
                            <td>{{ $receipt['number'] }}</td>
                            <td class="text-right">{{ number_format($receipt['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="notes">
        <h4>{{ __('documents.soa_aging') }}</h4>
        <table class="aging">
            <thead>
                <tr>
                    <th></th>
                    <th>{{ __('documents.soa_aging_current') }}</th>
                    <th>{{ __('documents.soa_aging_1_30') }}</th>
                    <th>{{ __('documents.soa_aging_31_60') }}</th>
                    <th>{{ __('documents.soa_aging_61_90') }}</th>
                    <th>{{ __('documents.soa_aging_over_90') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td></td>
                    <td>{{ number_format($aging['current'] ?? 0, 2) }}</td>
                    <td>{{ number_format($aging['1_30'] ?? 0, 2) }}</td>
                    <td>{{ number_format($aging['31_60'] ?? 0, 2) }}</td>
                    <td>{{ number_format($aging['61_90'] ?? 0, 2) }}</td>
                    <td>{{ number_format($aging['over_90'] ?? 0, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if ($unresolvedExceptionsTotal > 0)
        <div class="notes">
            <h4>{{ __('documents.soa_unresolved_exceptions_total') }}</h4>
            <div>{{ number_format($unresolvedExceptionsTotal, 2) }}</div>
        </div>
    @endif
</body>
</html>
