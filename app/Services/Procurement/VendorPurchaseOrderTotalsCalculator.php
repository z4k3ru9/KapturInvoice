<?php

namespace App\Services\Procurement;

use App\Models\VendorPurchaseOrder;

/**
 * Recomputes a Vendor PO's `total` from its items, mirroring
 * App\Services\QuotationTotalsCalculator's "recompute from source, don't
 * trust hand-edited totals" approach — see
 * docs/rebuild/specs/05-procurement-and-delivery/Specs.md. Deliberately
 * simpler than the quotation/invoice calculators: a Vendor PO item has no
 * discount or tax columns (App\Models\VendorPurchaseOrderItem), so each
 * line is just `quantity * unit_cost`.
 */
class VendorPurchaseOrderTotalsCalculator
{
    public function recalculate(VendorPurchaseOrder $po): VendorPurchaseOrder
    {
        $po->loadMissing('items');

        $total = 0;

        foreach ($po->items as $item) {
            $lineTotal = round((float) $item->quantity * (float) $item->unit_cost, 2);

            if ((float) $item->line_total !== $lineTotal) {
                $item->forceFill(['line_total' => $lineTotal])->saveQuietly();
            }

            $total += $lineTotal;
        }

        $po->forceFill(['total' => round($total, 2)])->saveQuietly();

        return $po;
    }
}
