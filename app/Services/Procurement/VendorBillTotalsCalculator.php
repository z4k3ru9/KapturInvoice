<?php

namespace App\Services\Procurement;

use App\Models\VendorBill;

/**
 * Recomputes a Vendor Bill's `total` (and, since it changes as a
 * consequence, `balance`) from its items — mirrors
 * App\Services\Procurement\VendorPurchaseOrderTotalsCalculator. Each
 * VendorBillItem's own `line_total` is its `net_amount` (less this line's
 * own optional discount — flat amount or percentage, GitHub issue #18)
 * plus `tax_amount` (App\Models\VendorBillItem), since a bill (unlike a
 * Vendor PO) carries the vendor's actual tax breakdown. The discount
 * reduces only the pre-tax net base, per this app's "discounts apply
 * before tax" rule — `net_amount`/`tax_amount` themselves stay exactly as
 * entered (the vendor's own billed breakdown) so a line's history isn't
 * rewritten; `tax_amount` is never recomputed off the discounted base,
 * since it is the vendor's actually-charged, nonrecoverable tax
 * (FINALIZED-DECISIONS.md §3 — no input-tax-credit engine here). Never
 * touches `amount_paid` or `status` — those are
 * App\Services\Procurement\RecalculateVendorBillPayments' job, driven by
 * recorded VendorPayment rows, not by item edits.
 */
class VendorBillTotalsCalculator
{
    public function recalculate(VendorBill $bill): VendorBill
    {
        $bill->loadMissing('items');

        $total = 0;

        foreach ($bill->items as $item) {
            $netAmount = (float) $item->net_amount;

            $discount = $item->discount_is_percentage
                ? $netAmount * ((float) $item->discount / 100)
                : (float) $item->discount;

            $discountedNet = round($netAmount - $discount, 2);
            $lineTotal = round($discountedNet + (float) $item->tax_amount, 2);

            if ((float) $item->line_total !== $lineTotal) {
                $item->forceFill(['line_total' => $lineTotal])->saveQuietly();
            }

            $total += $lineTotal;
        }

        $total = round($total, 2);
        $paid = round((float) $bill->amount_paid, 2);

        $bill->forceFill([
            'total' => $total,
            'balance' => round($total - $paid, 2),
        ])->saveQuietly();

        return $bill;
    }
}
