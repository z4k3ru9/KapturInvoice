<?php

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Pure computation of a client's Statement of Account for a reporting
 * period — Phase 06B Slice 1
 * (docs/rebuild/specs/06b-ux-browser-soa/Specs.md): "Keep SOA calculations
 * in a tested action/service, not in Blade or Livewire." Never persists
 * anything — App\Actions\Reports\GenerateStatementOfAccount is the only
 * writer of a real `StatementOfAccount` row; a preview calls `build()`
 * directly.
 *
 * "SOA uses document dates for invoices and credit documents, and
 * verification dates for payments and receipts." — same Specs.md.
 *
 * Every query below scopes explicitly by `$client->company_id` AND
 * `$client->id` rather than relying solely on BelongsToCompany's global
 * scope, which only auto-applies inside a Filament request with a
 * resolved tenant (see App\Models\Concerns\BelongsToCompany's own
 * docblock) — CLAUDE.md: "Treat company scoping ... as blocking safety
 * boundaries."
 */
class BuildStatementOfAccount
{
    /** @var list<InvoiceStatus> Invoice statuses whose *face total* counts toward the opening balance. */
    private const OPENING_BALANCE_STATUSES = [
        InvoiceStatus::Issued,
        InvoiceStatus::Partial,
        InvoiceStatus::Overdue,
        InvoiceStatus::Paid,
    ];

    /** @return array<string, mixed> */
    public function build(Client $client, DateTimeInterface|string $periodStart, DateTimeInterface|string $periodEnd): array
    {
        $companyId = $client->company_id;
        $clientId = $client->id;
        $periodStart = CarbonImmutable::parse($periodStart)->startOfDay();
        $periodEnd = CarbonImmutable::parse($periodEnd)->endOfDay();

        // `invoice_date`/`credit_date` are DATE columns (cast 'date', no
        // time component) — compare them against plain Y-m-d strings.
        // Comparing a date-only column against a full datetime string
        // (e.g. "2026-08-01 00:00:00") would break on SQLite (used in
        // dev/test): dates are stored as text there, and "2026-08-01" is
        // lexicographically *less than* "2026-08-01 00:00:00", so a
        // ">=" bound with a time component would wrongly exclude a row
        // dated exactly on that day.
        $periodStartDate = $periodStart->toDateString();
        $periodEndDate = $periodEnd->toDateString();

        $openingBalance = $this->openingBalance($companyId, $clientId, $periodStart, $periodStartDate);

        $invoiceRows = $this->invoiceRows($companyId, $clientId, $periodStartDate, $periodEndDate);
        $creditRows = $this->creditRows($companyId, $clientId, $periodStartDate, $periodEndDate);
        $paymentRows = $this->paymentRows($companyId, $clientId, $periodStart, $periodEnd);
        $receiptRows = $this->receiptRows($companyId, $clientId, $periodStart, $periodEnd);

        $invoicesTotal = round(array_sum(array_column($invoiceRows, 'balance_contribution')), 2);
        $creditsTotal = round(array_sum(array_column($creditRows, 'balance_contribution')), 2);
        $paymentsTotal = round(array_sum(array_column($paymentRows, 'balance_contribution')), 2);
        $unresolvedExceptionsTotal = round(array_sum(array_column($creditRows, 'unresolved_amount')), 2);

        // The core running-balance formula: what was owed at the start of
        // the period, plus what was newly invoiced, minus what was written
        // off by a confirmed credit or settled by a verified payment.
        // Void/Amended invoice rows, unconfirmed credits, and
        // Reversed payments all contribute 0 via their *_contribution
        // field above, so they never inflate or deflate this number even
        // though they remain visible in their own row list.
        $closingBalance = round($openingBalance + $invoicesTotal - $creditsTotal - $paymentsTotal, 2);

        return [
            'opening_balance' => $openingBalance,
            'invoices' => $invoiceRows,
            'credits' => $creditRows,
            'receipts' => $receiptRows,
            'payments' => $paymentRows,
            'closing_balance' => $closingBalance,
            'unresolved_exceptions_total' => $unresolvedExceptionsTotal,
            'aging' => $this->aging($companyId, $clientId, $periodEnd),
        ];
    }

    private function openingBalance(int $companyId, int $clientId, CarbonImmutable $periodStart, string $periodStartDate): float
    {
        $invoicedBeforePeriod = (float) Invoice::query()
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->whereIn('status', self::OPENING_BALANCE_STATUSES)
            ->whereNotNull('invoice_date')
            ->where('invoice_date', '<', $periodStartDate)
            ->sum('total');

        // The full verified payment amount, not just its allocated
        // portion — a Codex review finding on PR #4: summing only active
        // PaymentAllocation rows here, while paymentRows() below (the
        // in-period bucket) subtracts each payment's full amount, meant
        // the very same account result changed depending only on which
        // side of periodStart a payment's verified_at happened to fall —
        // a 1,000 payment allocated just 300 reduced the opening balance
        // by 300 but the closing balance by the full 1,000 had it been
        // verified one day later. An unapplied/overpayment portion is
        // still money the client has paid and must reduce what they owe
        // either way, so both bucket use the same payment-event basis.
        $paidBeforePeriod = (float) Payment::query()
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->where('status', PaymentStatus::Verified)
            ->whereNotNull('verified_at')
            ->where('verified_at', '<', $periodStart)
            ->sum('amount');

        $creditedBeforePeriod = (float) Credit::query()
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->whereNotNull('credit_date')
            ->where('credit_date', '<', $periodStartDate)
            ->get()
            ->filter(fn (Credit $credit) => $this->isConfirmed($credit))
            ->sum(fn (Credit $credit) => (float) $credit->amount);

        return round($invoicedBeforePeriod - $paidBeforePeriod - $creditedBeforePeriod, 2);
    }

    /** @return list<array<string, mixed>> */
    private function invoiceRows(int $companyId, int $clientId, string $periodStartDate, string $periodEndDate): array
    {
        return Invoice::query()
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->whereNotNull('invoice_date')
            ->whereBetween('invoice_date', [$periodStartDate, $periodEndDate])
            ->orderBy('invoice_date')
            ->get()
            ->map(function (Invoice $invoice) {
                // Void/Amended rows stay visible with their status label
                // but never inflate the balance — Specs.md: "excluded
                // amounts must not inflate the current outstanding
                // balance". An amendment/reissue is itself a brand-new
                // Invoice row with its own status/total, so it is counted
                // on its own line rather than double-counted here.
                // Draft/Approved/Cancelled are also excluded — they are
                // not yet (or never will be) a real receivable, matching
                // the stricter status list openingBalance() already uses
                // (a Codex review finding on PR #4: this method used to
                // only exclude Void/Amended, so a same-period Draft or
                // Approved invoice inflated the closing balance).
                $excluded = in_array($invoice->status, [
                    InvoiceStatus::Void,
                    InvoiceStatus::Amended,
                    InvoiceStatus::Draft,
                    InvoiceStatus::Approved,
                    InvoiceStatus::Cancelled,
                ], true);

                return [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'document_date' => optional($invoice->invoice_date)->toDateString(),
                    'type' => $invoice->type?->value,
                    'status' => $invoice->status?->value,
                    'status_label' => $invoice->status?->getLabel(),
                    'total' => (float) $invoice->total,
                    'balance_contribution' => $excluded ? 0.0 : (float) $invoice->total,
                ];
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function creditRows(int $companyId, int $clientId, string $periodStartDate, string $periodEndDate): array
    {
        return Credit::query()
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->whereNotNull('credit_date')
            ->whereBetween('credit_date', [$periodStartDate, $periodEndDate])
            ->orderBy('credit_date')
            ->get()
            ->map(function (Credit $credit) {
                $confirmed = $this->isConfirmed($credit);
                $amount = (float) $credit->amount;

                return [
                    'id' => $credit->id,
                    'number' => $credit->number,
                    'document_date' => optional($credit->credit_date)->toDateString(),
                    'amount' => $amount,
                    'confirmed' => $confirmed,
                    'balance_contribution' => $confirmed ? $amount : 0.0,
                    'unresolved_amount' => $confirmed ? 0.0 : $amount,
                ];
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function paymentRows(int $companyId, int $clientId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        return Payment::query()
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->whereNotNull('verified_at')
            ->whereBetween('verified_at', [$periodStart, $periodEnd])
            ->orderBy('verified_at')
            ->get()
            ->map(function (Payment $payment) {
                // A payment that was verified and later reversed keeps its
                // original verified_at (see App\Actions\Receivables\
                // ReverseCustomerPayment), so it still surfaces here — with
                // a Reversed status label and 0 balance contribution.
                $isReversed = $payment->status === PaymentStatus::Reversed;

                return [
                    'id' => $payment->id,
                    'reference' => $payment->reference,
                    'method' => $payment->method,
                    'verified_at' => optional($payment->verified_at)->toDateString(),
                    'amount' => (float) $payment->amount,
                    'status' => $payment->status?->value,
                    'status_label' => $payment->status?->getLabel(),
                    'balance_contribution' => $isReversed ? 0.0 : (float) $payment->amount,
                ];
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function receiptRows(int $companyId, int $clientId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        return Receipt::query()
            ->where('company_id', $companyId)
            ->whereHas('payment', fn ($query) => $query->where('client_id', $clientId))
            ->whereBetween('issued_at', [$periodStart, $periodEnd])
            ->with('payment')
            ->orderBy('issued_at')
            ->get()
            ->map(fn (Receipt $receipt) => [
                'id' => $receipt->id,
                'number' => $receipt->number,
                'issued_at' => optional($receipt->issued_at)->toDateString(),
                'payment_id' => $receipt->payment_id,
                'amount' => (float) ($receipt->payment?->amount ?? 0),
                'method' => $receipt->payment?->method,
            ])
            ->all();
    }

    /**
     * Sums every invoice's outstanding balance *as of `$periodEnd`*,
     * bucketed by days overdue from `due_date` — reconstructed from
     * PaymentAllocation history rather than read off each invoice's
     * live `balance` column (a Codex review finding on PR #4: the live
     * column reflects payments verified *after* `$periodEnd` too, so a
     * historical SOA's aging silently changed with later activity and
     * stopped reconciling with that same historical `closing_balance`).
     * Only invoices dated on/before `$periodEnd` are considered — one
     * dated after it hasn't happened yet as of that reporting instant.
     *
     * @return array{current: float, "1_30": float, "31_60": float, "61_90": float, over_90: float}
     */
    private function aging(int $companyId, int $clientId, CarbonImmutable $periodEnd): array
    {
        $buckets = [
            'current' => 0.0,
            '1_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            'over_90' => 0.0,
        ];

        Invoice::query()
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->whereNotIn('status', [InvoiceStatus::Void, InvoiceStatus::Amended, InvoiceStatus::Cancelled, InvoiceStatus::Draft, InvoiceStatus::Approved])
            ->whereNotNull('invoice_date')
            ->where('invoice_date', '<=', $periodEnd->toDateString())
            ->get()
            ->each(function (Invoice $invoice) use (&$buckets, $periodEnd, $companyId, $clientId) {
                $paidAsOfPeriodEnd = (float) PaymentAllocation::query()
                    ->where('invoice_id', $invoice->id)
                    ->where('is_active', true)
                    ->whereHas('payment', function ($query) use ($companyId, $clientId, $periodEnd) {
                        $query->where('company_id', $companyId)
                            ->where('client_id', $clientId)
                            ->where('status', PaymentStatus::Verified)
                            ->whereNotNull('verified_at')
                            ->where('verified_at', '<=', $periodEnd);
                    })
                    ->sum('amount');

                $balance = round((float) $invoice->total - $paidAsOfPeriodEnd, 2);

                if ($balance <= 0.0) {
                    return;
                }

                $dueDate = $invoice->due_date;

                // Compare at day granularity (not $periodEnd's end-of-day
                // instant) so a due date exactly N days before the period
                // end lands on a clean integer, e.g. due 10 days ago is
                // exactly 10, never 10.999...
                $daysOverdue = $dueDate ? $dueDate->diffInDays($periodEnd->startOfDay(), false) : 0;

                $bucket = match (true) {
                    $daysOverdue <= 0 => 'current',
                    $daysOverdue <= 30 => '1_30',
                    $daysOverdue <= 60 => '31_60',
                    $daysOverdue <= 90 => '61_90',
                    default => 'over_90',
                };

                $buckets[$bucket] = round($buckets[$bucket] + $balance, 2);
            });

        return $buckets;
    }

    /**
     * Whether a credit counts toward the confirmed balance. The imported
     * historical-credit quarantine flag doesn't exist on App\Models\Credit
     * yet (that's Phase 07's InvoiceNinja historical-credit import) — so
     * every existing Credit row is treated as confirmed until that column
     * is added, per docs/rebuild/specs/FINALIZED-DECISIONS.md §7. Reading
     * the attribute defensively (rather than requiring the column) keeps
     * this service ready for that column to land without another change
     * here.
     */
    private function isConfirmed(Credit $credit): bool
    {
        return ! (bool) ($credit->getAttributes()['is_quarantined'] ?? false);
    }
}
