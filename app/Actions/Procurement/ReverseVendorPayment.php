<?php

namespace App\Actions\Procurement;

use App\Enums\VendorPaymentStatus;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\VendorPaymentReversal;
use App\Services\AuditLogger;
use App\Services\Procurement\RecalculateVendorBillPayments;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "Later corrections create linked amendments or reversals without
 * mutating the original event." — docs/rebuild/specs/
 * FINALIZED-DECISIONS.md §7. Mirrors
 * App\Actions\Receivables\ReverseCustomerPayment: the vendor payment
 * itself moves to VendorPaymentStatus::Reversed but is never deleted;
 * any receipt already issued for it is untouched; and
 * App\Services\Procurement\RecalculateVendorBillPayments already
 * excludes non-Verified payments from its sum, so the affected bill's
 * balance rises back once recalculated below.
 */
class ReverseVendorPayment
{
    public function __construct(
        private AuditLogger $auditLogger,
        private RecalculateVendorBillPayments $recalculator,
    ) {}

    public function reverse(VendorPayment $payment, string $reason, User $actor): VendorPayment
    {
        if (! in_array($payment->status, [VendorPaymentStatus::Pending, VendorPaymentStatus::Verified], true)) {
            throw new RuntimeException('Only a pending or verified vendor payment can be reversed.');
        }

        DB::transaction(function () use ($payment, $reason, $actor) {
            $payment->forceFill(['status' => VendorPaymentStatus::Reversed])->save();

            VendorPaymentReversal::create([
                'vendor_payment_id' => $payment->id,
                'reason' => $reason,
                'reversed_by_user_id' => $actor->id,
                'reversed_at' => now(),
            ]);

            $this->recalculator->recalculate($payment->vendorBill);
        });

        $this->auditLogger->record(
            $payment->company,
            'vendor_payment.reversed',
            $payment,
            [],
            [],
            $reason,
        );

        return $payment->fresh();
    }
}
