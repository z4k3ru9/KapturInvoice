<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRate;

/**
 * Recomputes an invoice's subtotal/tax_total/total/balance from its items
 * (and each item's applied taxes) rather than trusting values edited by
 * hand — the denormalized-ledger risk flagged in
 * docs/invoiceninja-v4-schema-reference.md §5.
 *
 * Call recalculate() after saving an invoice's items/taxes (e.g. from a
 * Filament relation manager's afterSave/afterDelete hooks).
 */
class InvoiceTotalsCalculator
{
    /**
     * Replaces an item's applied taxes with the given tax rates, snapshotting
     * each rate's name/rate at the moment it's applied (see
     * invoice_item_taxes migration) rather than referencing it live.
     *
     * @param  array<int>  $taxRateIds
     */
    public function syncItemTaxes(InvoiceItem $item, array $taxRateIds): void
    {
        $lineGross = $item->quantity * $item->unit_cost;
        $discount = $item->discount_is_percentage
            ? $lineGross * ($item->discount / 100)
            : $item->discount;
        $lineTotal = round($lineGross - $discount, 2);

        $item->taxes()->delete();

        $taxRates = TaxRate::query()->whereKey($taxRateIds)->get();

        foreach ($taxRates as $taxRate) {
            $item->taxes()->create([
                'tax_rate_id' => $taxRate->id,
                'name' => $taxRate->name,
                'rate' => $taxRate->rate,
                'amount' => round($lineTotal * ($taxRate->rate / 100), 2),
            ]);
        }
    }

    public function recalculate(Invoice $invoice): Invoice
    {
        $invoice->loadMissing('items.taxes');

        $subtotal = 0;

        foreach ($invoice->items as $item) {
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

        $documentDiscount = $invoice->discount_is_percentage
            ? $subtotal * ($invoice->discount / 100)
            : $invoice->discount;

        // syncItemTaxes() computes each tax row against the line's own
        // (post-line-discount) total, before any document-level discount is
        // known. That document discount must still reduce the *taxable base*
        // before tax — so re-derive each line's taxable base here (line total
        // minus its proportional share of the document discount) and
        // recompute tax from the stored rate against that base, rather than
        // taxing the undiscounted subtotal and subtracting the discount
        // afterward. Recomputing from `rate` (not scaling the previously
        // stored `amount`) keeps this idempotent across repeated calls.
        $taxTotal = 0;

        foreach ($invoice->items as $item) {
            $lineTotal = (float) $item->line_total;

            $lineShareOfDiscount = $subtotal > 0
                ? $documentDiscount * ($lineTotal / $subtotal)
                : 0;

            $taxableBase = max(0, $lineTotal - $lineShareOfDiscount);

            foreach ($item->taxes as $itemTax) {
                $adjustedAmount = round($taxableBase * ((float) $itemTax->rate / 100), 2);

                if ((float) $itemTax->amount !== $adjustedAmount) {
                    $itemTax->forceFill(['amount' => $adjustedAmount])->saveQuietly();
                }

                $taxTotal += $adjustedAmount;
            }
        }

        $total = round($subtotal - $documentDiscount + $taxTotal, 2);
        $balance = round($total - $invoice->amount_paid, 2);

        $invoice->forceFill([
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => $total,
            'balance' => $balance,
        ])->saveQuietly();

        return $invoice;
    }
}
