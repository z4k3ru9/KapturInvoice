@php
    // Same document-language resolution as every other printed document
    // (App\Models\Invoice::resolveDocumentLanguage() and friends) — see
    // App\Models\StatementOfAccount::resolveDocumentLanguage(). Only
    // affects the labels below, never any computed number.
    app()->setLocale($soa->resolveDocumentLanguage());

    $snapshot = $soa->snapshot ?? [];
    $openingBalance = (float) ($snapshot['opening_balance'] ?? 0);
    $closingBalance = (float) ($snapshot['closing_balance'] ?? 0);
    $aging = $snapshot['aging'] ?? [];
    $unresolvedExceptionsTotal = (float) ($snapshot['unresolved_exceptions_total'] ?? 0);
    $currency = $soa->client->currency_code ?: $soa->company->currency_code;

    // Identical composition to resources/views/pdf/statement-of-account.blade.php's
    // own inline @php block — a chronological ledger over the same
    // already-computed balance_contribution fields, never a second
    // calculation. Kept in sync deliberately rather than extracted into a
    // shared partial, since dompdf's block-only rendering and this page's
    // Tailwind/TallStackUI markup diverge everywhere else.
    $ledgerRows = collect();

    foreach (($snapshot['invoices'] ?? []) as $row) {
        $ledgerRows->push([
            'date' => $row['document_date'],
            'document' => $row['number'],
            'document_url' => $row['id'] ? route('tallstack.invoices.edit', [$soa->company, $row['id']]) : null,
            'description' => __('documents.type_invoice').' — '.$row['status_label'],
            'debit' => (float) $row['balance_contribution'],
            'credit' => 0.0,
        ]);
    }

    foreach (($snapshot['credits'] ?? []) as $row) {
        $ledgerRows->push([
            'date' => $row['document_date'],
            'document' => $row['number'],
            'document_url' => null,
            'description' => __('documents.type_credit').($row['confirmed'] ? '' : ' — '.__('documents.soa_unresolved')),
            'debit' => 0.0,
            'credit' => (float) $row['balance_contribution'],
        ]);
    }

    foreach (($snapshot['payments'] ?? []) as $row) {
        $ledgerRows->push([
            'date' => $row['verified_at'],
            'document' => $row['reference'] ?: ('#'.$row['id']),
            'document_url' => $row['id'] ? route('tallstack.payments.allocate', [$soa->company, $row['id']]) : null,
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
<div class="max-w-[1000px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[
        ['label' => $company->name],
        ['label' => 'Clients', 'url' => route('tallstack.clients', $company)],
        ['label' => $client->name, 'url' => route('tallstack.clients.show', [$company, $client])],
        ['label' => 'Statement of Account'],
    ]" title="Statement of Account">
        <x-slot:badge>
            @if ($isPreview)
                <x-badge text="Preview" color="amber" />
            @else
                <x-badge text="Issued" color="green" />
            @endif
        </x-slot:badge>
    </x-tallstack.page-header>

    {{-- The A4-proportioned document itself. Everything inside this card
         is the printed content — admin-chrome actions live below it, per
         prompt 17's own "below the document itself (admin-chrome, not
         part of the printed page)" instruction. --}}
    <div class="relative bg-white text-gray-900 rounded-lg border border-gray-200 shadow-sm overflow-hidden mx-auto w-full"
         style="max-width: 794px; aspect-ratio: 210 / 297; min-height: 1123px;">

        {{-- Large diagonal "PREVIEW — NOT YET GENERATED" banner across the
             top of the document, per prompt 17 — visually unmistakable,
             absent entirely on an Issued SOA. Confined to the top corner
             (rather than a full-page diagonal) so it never obscures the
             ledger table's own numbers underneath. --}}
        @if ($isPreview)
            <div class="pointer-events-none select-none absolute top-0 inset-x-0 z-10 h-36 overflow-hidden">
                <div class="rotate-[-32deg] bg-amber-500 text-white font-extrabold tracking-widest text-center py-3 shadow-lg"
                     style="width: 140%; margin-top: -0.5rem; margin-left: -20%;">
                    {{ __('documents.soa_preview') }}
                </div>
            </div>
        @endif

        <div class="p-8 sm:p-10 flex flex-col gap-6">
            {{-- Letterhead. --}}
            <div class="flex items-start justify-between gap-6">
                <div>
                    @if ($logo = $soa->company->getLogoDataUri())
                        <img src="{{ $logo }}" alt="{{ $soa->company->name }}" class="max-h-12 max-w-[220px] mb-2">
                    @endif
                    <div class="font-bold text-lg">{{ $soa->company->name }}</div>
                    @if ($soa->company->address_line_1)
                        <div class="text-sm text-gray-500">{{ $soa->company->address_line_1 }}</div>
                    @endif
                    @if ($soa->company->email)
                        <div class="text-sm text-gray-500">{{ $soa->company->email }}</div>
                    @endif
                    @if ($soa->company->tax_number)
                        <div class="text-sm text-gray-500">{{ __('documents.tax_id') }}: {{ $soa->company->tax_number }}</div>
                    @endif
                </div>
                <div class="text-right">
                    <div class="font-extrabold text-xl uppercase">{{ __('documents.soa_title') }}</div>
                    @if ($soa->number)
                        <div class="text-sm text-gray-500 mt-1">{{ $soa->number }}</div>
                    @endif
                    <div class="text-sm text-gray-500">
                        {{ __('documents.soa_period') }}:
                        {{ \Illuminate\Support\Carbon::parse($soa->period_start)->translatedFormat('d M Y') }}
                        &ndash;
                        {{ \Illuminate\Support\Carbon::parse($soa->period_end)->translatedFormat('d M Y') }}
                    </div>
                    @if ($soa->generated_at)
                        <div class="text-sm text-gray-500">{{ __('documents.soa_generated_at') }}: {{ $soa->generated_at->translatedFormat('d M Y H:i') }}</div>
                    @endif
                </div>
            </div>

            {{-- Client. --}}
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">{{ __('documents.soa_client') }}</h4>
                <div class="font-bold">{{ $soa->client->name }}</div>
                @if ($soa->client->address_line_1)
                    <div class="text-sm text-gray-500">{{ $soa->client->address_line_1 }}</div>
                @endif
                @if ($soa->client->email)
                    <div class="text-sm text-gray-500">{{ $soa->client->email }}</div>
                @endif
                @if ($soa->client->tax_number)
                    <div class="text-sm text-gray-500">{{ __('documents.tax_id') }}: {{ $soa->client->tax_number }}</div>
                @endif
            </div>

            {{-- Chronological transaction ledger — tabular numerals,
                 right-aligned Debit/Credit/Balance columns. --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="border-b-2 border-gray-800 text-xs uppercase text-gray-400">
                            <th class="text-left py-2 pr-2">{{ __('documents.soa_date') }}</th>
                            <th class="text-left py-2 pr-2">{{ __('documents.soa_document') }}</th>
                            <th class="text-left py-2 pr-2">{{ __('documents.soa_description') }}</th>
                            <th class="text-right py-2 px-2">{{ __('documents.soa_debit') }}</th>
                            <th class="text-right py-2 px-2">{{ __('documents.soa_credit') }}</th>
                            <th class="text-right py-2 pl-2">{{ __('documents.soa_balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-gray-200">
                            <td colspan="5" class="py-2 pr-2 font-bold">{{ __('documents.soa_opening_balance') }}</td>
                            <td class="py-2 pl-2 text-right font-bold tabular-nums">{{ \App\Support\Dashboard\Money::format($openingBalance, $currency) }}</td>
                        </tr>
                        @forelse ($ledgerRows as $row)
                            <tr class="border-b border-gray-100">
                                <td class="py-2 pr-2 whitespace-nowrap">{{ $row['date'] }}</td>
                                <td class="py-2 pr-2 whitespace-nowrap">
                                    @if ($row['document_url'])
                                        <a href="{{ $row['document_url'] }}" class="text-blue-600 hover:underline">{{ $row['document'] }}</a>
                                    @else
                                        {{ $row['document'] }}
                                    @endif
                                </td>
                                <td class="py-2 pr-2 text-gray-500">{{ $row['description'] }}</td>
                                <td class="py-2 px-2 text-right tabular-nums">{{ $row['debit'] > 0 ? \App\Support\Dashboard\Money::format($row['debit'], $currency) : '' }}</td>
                                <td class="py-2 px-2 text-right tabular-nums">{{ $row['credit'] > 0 ? \App\Support\Dashboard\Money::format($row['credit'], $currency) : '' }}</td>
                                <td class="py-2 pl-2 text-right tabular-nums">{{ \App\Support\Dashboard\Money::format($row['balance'], $currency) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-4 text-center text-gray-400">{{ __('documents.soa_no_activity') }}</td>
                            </tr>
                        @endforelse
                        {{-- Closing balance — visually separated (top border,
                             bold) from the transaction rows above it, per
                             prompt 17. --}}
                        <tr class="border-t-2 border-gray-800">
                            <td colspan="5" class="py-2 pr-2 font-bold">{{ __('documents.soa_closing_balance') }}</td>
                            <td class="py-2 pl-2 text-right font-bold tabular-nums">{{ \App\Support\Dashboard\Money::format($closingBalance, $currency) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Aging summary — explicitly labeled "as of [period end]" per
                 prompt 17, since it's a real, easy-to-misread distinction
                 from "as of today". --}}
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">
                    {{ __('documents.soa_aging') }} — {{ __('documents.soa_period') }} {{ \Illuminate\Support\Carbon::parse($soa->period_end)->translatedFormat('d M Y') }}
                </h4>
                <div class="grid grid-cols-5 gap-2 text-right text-sm border border-gray-200 rounded-md overflow-hidden">
                    <div class="bg-gray-50 p-2">
                        <div class="text-xs text-gray-400">{{ __('documents.soa_aging_current') }}</div>
                        <div class="font-bold tabular-nums">{{ \App\Support\Dashboard\Money::format($aging['current'] ?? 0, $currency) }}</div>
                    </div>
                    <div class="bg-gray-50 p-2">
                        <div class="text-xs text-gray-400">{{ __('documents.soa_aging_1_30') }}</div>
                        <div class="font-bold tabular-nums">{{ \App\Support\Dashboard\Money::format($aging['1_30'] ?? 0, $currency) }}</div>
                    </div>
                    <div class="bg-gray-50 p-2">
                        <div class="text-xs text-gray-400">{{ __('documents.soa_aging_31_60') }}</div>
                        <div class="font-bold tabular-nums">{{ \App\Support\Dashboard\Money::format($aging['31_60'] ?? 0, $currency) }}</div>
                    </div>
                    <div class="bg-gray-50 p-2">
                        <div class="text-xs text-gray-400">{{ __('documents.soa_aging_61_90') }}</div>
                        <div class="font-bold tabular-nums">{{ \App\Support\Dashboard\Money::format($aging['61_90'] ?? 0, $currency) }}</div>
                    </div>
                    <div class="bg-red-50 p-2">
                        <div class="text-xs text-red-500">{{ __('documents.soa_aging_over_90') }}</div>
                        <div class="font-bold tabular-nums text-red-700">{{ \App\Support\Dashboard\Money::format($aging['over_90'] ?? 0, $currency) }}</div>
                    </div>
                </div>
            </div>

            @if ($unresolvedExceptionsTotal > 0)
                <div class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md p-2">
                    {{ __('documents.soa_unresolved_exceptions_total') }}: {{ \App\Support\Dashboard\Money::format($unresolvedExceptionsTotal, $currency) }}
                </div>
            @endif
        </div>
    </div>

    {{-- Admin-chrome actions — not part of the printed page. --}}
    <div class="max-w-[794px] w-full mx-auto flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-lg p-4">
        @if ($isPreview)
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center w-full">
                <div class="flex items-center gap-3">
                    <x-date wire:model.live="periodStart" label="Period start" />
                    <x-date wire:model.live="periodEnd" label="Period end" />
                </div>
                <div class="flex-1"></div>
                <div class="flex flex-col items-start sm:items-end gap-1">
                    <x-button text="Generate & Issue" icon="document-check" color="green" wire:click="generateAndIssue" wire:confirm="Generate this Statement of Account? This becomes a permanent numbered record — cannot be un-generated." loading="generateAndIssue" spinner="dots" />
                    <span class="text-xs text-gray-400">This becomes a permanent numbered record — cannot be un-generated.</span>
                </div>
            </div>
        @else
            <div class="text-xs text-gray-400">This is a frozen, immutable snapshot — it cannot be edited or regenerated.</div>
            <div class="flex items-center gap-2">
                <x-button text="Download PDF" icon="document-arrow-down" color="gray" href="{{ route('statement-of-accounts.pdf', $soa->id) }}" target="_blank" />
                <x-button text="Email to client" icon="envelope" color="blue" wire:click="emailToClient" loading="emailToClient" spinner="dots" />
            </div>
        @endif
    </div>
</div>
