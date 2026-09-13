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

        $receipt = Receipt::create([
            'company_id' => $payment->company_id,
            'payment_id' => $payment->id,
            'number' => $number,
            'issued_at' => now(),
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
