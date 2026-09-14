<?php

namespace App\Actions\Receivables;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\AuditLogger;
use App\Services\DocumentNumberGenerator;
use RuntimeException;

/**
 * "One verified payment event creates exactly one numbered receipt." —
 * docs/rebuild/specs/04-billing-and-receivables/Specs.md,
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §4. The `receipts.payment_id`
 * unique column backs this at the database level too; the existence
 * check here just gives a cleaner error message than the raw constraint
 * violation.
 *
 * Freezes a `snapshot` of the payment's amount/method/reference and its
 * then-current allocations — a Codex review finding on PR #4: without
 * this, downloading the receipt PDF re-rendered the payment's *live*
 * allocation history, so a later App\Actions\Receivables\
 * AmendPaymentAllocation silently changed what an already-issued receipt
 * showed. See App\Models\Receipt::$casts.
 */
class IssuePaymentReceipt
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function issue(Payment $payment): Receipt
    {
        if ($payment->status !== PaymentStatus::Verified) {
            throw new RuntimeException('Only a verified payment can be issued a receipt.');
        }

        if ($payment->receipt()->exists()) {
            throw new RuntimeException('A receipt has already been issued for this payment.');
        }

        $number = app(DocumentNumberGenerator::class)->next($payment->company, 'receipt');

        $payment->loadMissing('allocations.invoice');

        $receipt = Receipt::create([
            'company_id' => $payment->company_id,
            'payment_id' => $payment->id,
            'number' => $number,
            'issued_at' => now(),
            'snapshot' => [
                'amount' => (string) $payment->amount,
                'currency_code' => $payment->currency_code,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'allocations' => $payment->allocations->map(fn ($allocation) => [
                    'invoice_number' => $allocation->invoice?->number,
                    'amount' => (string) $allocation->amount,
                    'is_active' => $allocation->is_active,
                ])->all(),
            ],
        ]);

        $this->auditLogger->record(
            $payment->company,
            'payment.receipt_issued',
            $receipt,
            [],
            ['number' => $receipt->number],
        );

        return $receipt;
    }
}
