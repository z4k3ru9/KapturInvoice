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
        $taxTotal = 0;

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
            $taxTotal += $item->taxes->sum('amount');
        }

        $discount = $invoice->discount_is_percentage
            ? $subtotal * ($invoice->discount / 100)
            : $invoice->discount;

        $total = round($subtotal - $discount + $taxTotal, 2);
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
