<?php

namespace App\Actions\Procurement;

use App\Enums\VendorPaymentStatus;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\VendorPaymentAmendment;
use App\Services\AuditLogger;
use App\Services\Procurement\RecalculateVendorBillPayments;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "Later corrections create linked amendments or reversals without
 * mutating the original event." — docs/rebuild/specs/
 * FINALIZED-DECISIONS.md §7. Mirrors
 * App\Actions\Receivables\AmendPaymentAllocation's shape: only legal once
 * a receipt already exists (an unreceipted payment's amount is corrected
 * by re-recording, or reversed and re-recorded); never touches the
 * original VendorPaymentReceipt's own `number`/`issued_at`, only records
 * a before/after snapshot linked to it. Limited to correcting the
 * recorded `amount` — moving a payment to a different vendor bill goes
 * through ReverseVendorPayment + a fresh RecordVendorPayment instead,
 * since that also re-derives a different Vendor PO's payment ceiling.
 *
 * Re-validates the replacement amount against the same PO-wide
 * non-reversed payment ceiling App\Actions\Procurement\RecordVendorPayment
 * enforces at record time (a Codex review finding on PR #4: this
 * unconditionally saved any amount, so an amendment could silently raise
 * a payment above the approved PO plus variances with no variance
 * required).
 */
class AmendVendorPayment
{
    public function __construct(
        private AuditLogger $auditLogger,
        private RecalculateVendorBillPayments $recalculator,
    ) {}

    public function amend(VendorPayment $payment, float $newAmount, string $reason, User $actor): VendorPaymentAmendment
    {
        $receipt = $payment->receipt;

        if (! $receipt) {
            throw new RuntimeException('Only an already-receipted vendor payment can be amended — reverse and re-record it instead.');
        }

        if ($newAmount <= 0) {
            throw new RuntimeException('The amended amount must be greater than zero.');
        }

        $bill = $payment->vendorBill;
        $po = $bill->vendorPurchaseOrder;
        $ceiling = $po->paymentCeiling();

        // Every non-reversed payment across every bill on this PO,
        // excluding this payment's own current amount (about to be
        // replaced) — same ceiling check RecordVendorPayment runs.
        $otherPayments = round((float) VendorPayment::query()
            ->whereIn('vendor_bill_id', $po->bills()->pluck('id'))
            ->where('status', '!=', VendorPaymentStatus::Reversed->value)
            ->where('id', '!=', $payment->id)
            ->sum('amount'), 2);

        if (($otherPayments + $newAmount) > ($ceiling + 0.01)) {
            throw new RuntimeException(
                'This amendment would raise total payments on the vendor PO to '.($otherPayments + $newAmount).
                ", exceeding its approved payment ceiling of {$ceiling}. ".
                'An Owner or Admin must approve a VendorPoVariance before amending past it.'
            );
        }

        $before = ['amount' => (string) $payment->amount];
        $after = ['amount' => number_format(round($newAmount, 2), 2, '.', '')];

        $amendment = DB::transaction(function () use ($payment, $newAmount, $receipt, $reason, $actor, $before, $after) {
            $payment->forceFill(['amount' => round($newAmount, 2)])->save();

            $this->recalculator->recalculate($payment->vendorBill);

            return VendorPaymentAmendment::create([
                'vendor_payment_receipt_id' => $receipt->id,
                'reason' => $reason,
                'changes_before' => $before,
                'changes_after' => $after,
                'amended_by_user_id' => $actor->id,
                'amended_at' => now(),
            ]);
        });

        $this->auditLogger->record(
            $payment->company,
            'vendor_payment.amended',
            $receipt,
            $before,
            $after,
            $reason,
        );

        return $amendment;
    }
}
