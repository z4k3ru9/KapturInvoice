<?php

namespace App\Actions\Procurement;

use App\Enums\VendorPaymentStatus;
use App\Models\VendorPayment;
use App\Models\VendorPaymentReceipt;
use App\Services\AuditLogger;
use App\Services\DocumentNumberGenerator;
use RuntimeException;

/**
 * "One verified event produces one Vendor Payment Receipt." —
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §7. Mirrors
 * App\Actions\Receivables\IssuePaymentReceipt: the `vendor_payment_id`
 * unique column backs the one-per-event rule at the database level too;
 * the existence check here just gives a cleaner error message. This is
 * where the launch-code 'VPR' number (FINALIZED-DECISIONS.md §2) is now
 * assigned, not at VendorPayment record time.
 *
 * Freezes a `snapshot` of the payment's amount/method/reference/applied
 * bill — a Codex review finding on PR #4: without this, downloading the
 * receipt PDF re-rendered `vendor_payments.amount` live, so a later
 * App\Actions\Procurement\AmendVendorPayment silently changed what an
 * already-issued receipt showed. See
 * App\Models\VendorPaymentReceipt::$casts.
 */
class IssueVendorPaymentReceipt
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function issue(VendorPayment $payment): VendorPaymentReceipt
    {
        if ($payment->status !== VendorPaymentStatus::Verified) {
            throw new RuntimeException('Only a verified vendor payment can be issued a receipt.');
        }

        if ($payment->receipt()->exists()) {
            throw new RuntimeException('A receipt has already been issued for this vendor payment.');
        }

        $number = app(DocumentNumberGenerator::class)->next($payment->company, 'vendor_payment');

        $payment->loadMissing('vendorBill');

        $receipt = VendorPaymentReceipt::create([
            'company_id' => $payment->company_id,
            'vendor_payment_id' => $payment->id,
            'number' => $number,
            'issued_at' => now(),
            'snapshot' => [
                'amount' => (string) $payment->amount,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'vendor_bill_number' => $payment->vendorBill?->number,
                'vendor_bill_total' => $payment->vendorBill ? (string) $payment->vendorBill->total : null,
            ],
        ]);

        $this->auditLogger->record(
            $payment->company,
            'vendor_payment.receipt_issued',
            $receipt,
            [],
            ['number' => $receipt->number],
        );

        return $receipt;
    }
}
