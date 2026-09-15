<?php

namespace App\Services\Procurement;

use App\Models\VendorPurchaseOrder;

/**
 * Recomputes a Vendor PO's `total` from its items, mirroring
 * App\Services\QuotationTotalsCalculator's "recompute from source, don't
 * trust hand-edited totals" approach — see
 * docs/rebuild/specs/05-procurement-and-delivery/Specs.md. A Vendor PO
 * item has no tax columns, so each line is `quantity * unit_cost` less its
 * own optional discount (flat amount or percentage) — the same per-line
 * discount step App\Services\InvoiceTotalsCalculator::recalculate() applies
 * (App\Models\VendorPurchaseOrderItem, GitHub issue #18).
 */
class VendorPurchaseOrderTotalsCalculator
{
    public function recalculate(VendorPurchaseOrder $po): VendorPurchaseOrder
    {
        $po->loadMissing('items');

        $total = 0;

        foreach ($po->items as $item) {
            $lineGross = (float) $item->quantity * (float) $item->unit_cost;

            $discount = $item->discount_is_percentage
                ? $lineGross * ((float) $item->discount / 100)
                : (float) $item->discount;

            $lineTotal = round($lineGross - $discount, 2);

            if ((float) $item->line_total !== $lineTotal) {
                $item->forceFill(['line_total' => $lineTotal])->saveQuietly();
            }

            $total += $lineTotal;
        }

        $po->forceFill(['total' => round($total, 2)])->saveQuietly();

        return $po;
    }
}
