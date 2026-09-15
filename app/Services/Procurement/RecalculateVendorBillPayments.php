<?php

namespace App\Services\Procurement;

use App\Enums\VendorBillStatus;
use App\Enums\VendorPaymentStatus;
use App\Models\VendorBill;

/**
 * "Vendor bills support partial vendor payments and evidence" —
 * docs/rebuild/specs/05-procurement-and-delivery/Specs.md. Recomputes
 * `amount_paid`/`balance` from the sum of only Verified VendorPayment
 * rows against the bill — mirrors
 * App\Services\Receivables\RecalculateInvoiceReceivables exactly, now
 * that vendor payments carry the same parallel immutable event model
 * (docs/rebuild/specs/FINALIZED-DECISIONS.md §7): a Pending payment is
 * not yet a settled fact, and a Reversed one no longer counts. Only
 * auto-transitions status while the bill is already in a billable state
 * (Approved/PartiallyPaid) — Draft/Submitted/Cancelled are never touched
 * here. Called by every Procurement action that records, verifies, or
 * reverses a vendor payment.
 */
class RecalculateVendorBillPayments
{
    /** @var list<VendorBillStatus> */
    private const BILLABLE_STATES = [
        VendorBillStatus::Approved,
        VendorBillStatus::PartiallyPaid,
    ];

    public function recalculate(VendorBill $bill): VendorBill
    {
        $paid = round((float) $bill->payments()->where('status', VendorPaymentStatus::Verified->value)->sum('amount'), 2);
        $balance = round((float) $bill->total - $paid, 2);

        $attributes = [
            'amount_paid' => $paid,
            'balance' => $balance,
        ];

        if (in_array($bill->status, self::BILLABLE_STATES, true)) {
            $attributes['status'] = match (true) {
                $balance <= 0.00 => VendorBillStatus::Paid,
                $paid > 0 => VendorBillStatus::PartiallyPaid,
                default => VendorBillStatus::Approved,
            };
        }

        $bill->forceFill($attributes)->saveQuietly();

        return $bill->fresh();
    }
}
