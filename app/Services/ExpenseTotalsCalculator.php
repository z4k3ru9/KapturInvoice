<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\TaxRate;

/**
 * Recomputes an expense's subtotal/tax_total/total from `expense_taxes`,
 * mirroring InvoiceTotalsCalculator but for a single amount rather than a
 * list of line items — an expense's "subtotal" here is simply what the
 * form calls the base amount (an alias handled by the resource pages, see
 * ExpenseResource), not a separate stored column.
 */
class ExpenseTotalsCalculator
{
    /**
     * @param  array<int>  $taxRateIds
     */
    public function syncTaxes(Expense $expense, array $taxRateIds): void
    {
        $expense->taxes()->delete();

        $taxRates = TaxRate::query()->whereKey($taxRateIds)->get();

        foreach ($taxRates as $taxRate) {
            $expense->taxes()->create([
                'tax_rate_id' => $taxRate->id,
                'name' => $taxRate->name,
                'rate' => $taxRate->rate,
                'amount' => round($expense->subtotal * ($taxRate->rate / 100), 2),
            ]);
        }
    }

    public function recalculate(Expense $expense): Expense
    {
        $expense->loadMissing('taxes');

        $taxTotal = (float) $expense->taxes->sum('amount');
        $total = round((float) $expense->subtotal + $taxTotal, 2);

        $expense->forceFill([
            'tax_total' => round($taxTotal, 2),
            'total' => $total,
        ])->saveQuietly();

        return $expense;
    }
}
