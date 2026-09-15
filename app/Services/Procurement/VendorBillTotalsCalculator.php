<?php

namespace App\Services\Procurement;

use App\Models\VendorBill;

/**
 * Recomputes a Vendor Bill's `total` (and, since it changes as a
 * consequence, `balance`) from its items — mirrors
 * App\Services\Procurement\VendorPurchaseOrderTotalsCalculator. Each
 * VendorBillItem's own `line_total` is `net_amount + tax_amount`
 * (App\Models\VendorBillItem), since a bill (unlike a Vendor PO) carries
 * the vendor's actual tax breakdown. Never touches `amount_paid` or
 * `status` — those are App\Services\Procurement\RecalculateVendorBillPayments'
 * job, driven by recorded VendorPayment rows, not by item edits.
 */
class VendorBillTotalsCalculator
{
    public function recalculate(VendorBill $bill): VendorBill
    {
        $bill->loadMissing('items');

        $total = 0;

        foreach ($bill->items as $item) {
            $lineTotal = round((float) $item->net_amount + (float) $item->tax_amount, 2);

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
