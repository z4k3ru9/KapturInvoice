<?php

namespace App\Services;

use App\Models\Quotation;

/**
 * Recomputes a quotation's subtotal/total from its items, mirroring
 * InvoiceTotalsCalculator's "recompute from source, don't trust hand-
 * edited totals" approach. Deliberately does not compute tax: the
 * approved Indonesian PPN/DPP Nilai Lain formula against
 * `Quotation::pricing_mode` is Phase 04 scope
 * (App\Services\TaxCalculationService) — a
 * quotation's `total` here is its
 * pre-tax billable value, which is what Phase 03's job/milestone value
 * checks need.
 */
class QuotationTotalsCalculator
{
    public function recalculate(Quotation $quotation): Quotation
    {
        $quotation->loadMissing('items');

        $subtotal = 0;

        foreach ($quotation->items as $item) {
            $lineGross = $item->quantity * $item->unit_cost;

            $discount = $item->discount_is_percentage
                ? $lineGross * ($item->discount / 100)
                : $item->discount;

            $lineTotal = round($lineGross - $discount, 2);

            if ((float) $item->line_total !== $lineTotal) {
                $item->forceFill(['line_total' => $lineTotal])->saveQuietly();
            }

            $subtotal += $lineTotal;
        }

        $documentDiscount = $quotation->discount_is_percentage
            ? $subtotal * ($quotation->discount / 100)
            : $quotation->discount;

        $total = round($subtotal - $documentDiscount, 2);

        $quotation->forceFill([
            'subtotal' => round($subtotal, 2),
            'total' => $total,
        ])->saveQuietly();

        return $quotation;
    }
}
