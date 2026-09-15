<?php

namespace App\Services\Tax;

use App\Enums\PricingMode;
use App\Enums\TaxCategory;
use App\Models\CompanyTaxSetting;

/**
 * The authoritative calculation engine for
 * docs/rebuild/specs/04-billing-and-receivables/Specs.md: "Discounts apply
 * before tax in this order: line subtotal, line discount, global discount
 * allocation, taxable base, tax, total." Pure and stateless — no model
 * writes here; callers (App\Actions\Billing\IssueInvoice, the Livewire tax
 * scratchpad) persist the result.
 *
 * Karunia (tax_enabled = false) always returns zero tax regardless of
 * pricing mode or line tax_category. Axen applies the approved 12% PPN /
 * 11-12 DPP Nilai Lain factor (FINALIZED-DECISIONS.md §3) only to
 * StandardTaxable lines — the combined effective add-on rate is
 * numerator/denominator * rate (11/12 * 12% = 11%), which is why a
 * Rp10,000,000 exclusive line and a Rp11,100,000 inclusive line describe
 * the same transaction (the required tests for this phase).
 */
class TaxCalculationService
{
    /**
     * @param  array<int, array{quantity: float|string, unit_cost: float|string, discount: float|string, discount_is_percentage: bool, tax_category?: ?TaxCategory}>  $lines
     * @return array{
     *     lines: array<int, array{line_subtotal: float, discount_share: float, taxable_base: float, selling_price_basis: float, tax_amount: float, line_total_with_tax: float}>,
     *     subtotal: float,
     *     discount_total: float,
     *     taxable_base_total: float,
     *     tax_total: float,
     *     pre_round_total: float,
     *     rounding_adjustment: float,
     *     total: float,
     * }
     */
    public function calculate(
        array $lines,
        PricingMode $pricingMode,
        ?CompanyTaxSetting $taxSetting,
        float $documentDiscount = 0.0,
        bool $documentDiscountIsPercentage = false,
    ): array {
        $taxEnabled = $taxSetting?->tax_enabled ?? false;
        $rate = (float) ($taxSetting?->standard_tax_rate ?? 0) / 100;
        $factorNumerator = (int) ($taxSetting?->dpp_factor_numerator ?? 1);
        $factorDenominator = (int) ($taxSetting?->dpp_factor_denominator ?? 1);
        $factor = $factorDenominator > 0 ? $factorNumerator / $factorDenominator : 0;

        // 1. Line subtotal, then line discount.
        $lineSubtotals = [];
        $subtotal = 0.0;

        foreach ($lines as $line) {
            $lineGross = (float) $line['quantity'] * (float) $line['unit_cost'];
            $lineDiscount = ! empty($line['discount_is_percentage'])
                ? $lineGross * ((float) $line['discount'] / 100)
                : (float) $line['discount'];

            $lineSubtotal = round(max(0, $lineGross - $lineDiscount), 2);
            $lineSubtotals[] = $lineSubtotal;
            $subtotal += $lineSubtotal;
        }

        $subtotal = round($subtotal, 2);

        // 2. Global discount allocation — deterministic largest-remainder
        // policy so per-line shares sum to exactly the document discount,
        // per FINALIZED-DECISIONS.md §3.
        $documentDiscountAmount = $documentDiscountIsPercentage
            ? round($subtotal * ($documentDiscount / 100), 2)
            : round($documentDiscount, 2);
        $documentDiscountAmount = min($documentDiscountAmount, $subtotal);

        $discountShares = $this->allocateLargestRemainder($lineSubtotals, $documentDiscountAmount);

        // 3. Taxable base, 4. tax, per line — then 5. total.
        $result = [];
        $taxableBaseTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lineSubtotals as $index => $lineSubtotal) {
            $taxableBase = round(max(0, $lineSubtotal - $discountShares[$index]), 2);
            $taxCategory = $lines[$index]['tax_category'] ?? TaxCategory::StandardTaxable;
            $lineIsTaxable = $taxEnabled && $taxCategory === TaxCategory::StandardTaxable && $rate > 0;

            if (! $lineIsTaxable) {
                $result[] = [
                    'line_subtotal' => $lineSubtotal,
                    'discount_share' => $discountShares[$index],
                    'taxable_base' => $taxableBase,
                    'selling_price_basis' => $taxableBase,
                    'tax_amount' => 0.0,
                    'line_total_with_tax' => $taxableBase,
                ];

                $taxableBaseTotal += $taxableBase;

                continue;
            }

            if ($pricingMode === PricingMode::Inclusive) {
                // $taxableBase already includes tax — back out the
                // pre-tax selling price so tax = base - sellingPrice.
                $sellingPrice = round($taxableBase / (1 + ($factor * $rate)), 2);
                $taxAmount = round($taxableBase - $sellingPrice, 2);
                $lineTotalWithTax = $taxableBase;
            } else {
                $sellingPrice = $taxableBase;
                $taxAmount = round($sellingPrice * $factor * $rate, 2);
                $lineTotalWithTax = round($sellingPrice + $taxAmount, 2);
            }

            $result[] = [
                'line_subtotal' => $lineSubtotal,
                'discount_share' => $discountShares[$index],
                'taxable_base' => $sellingPrice,
                'selling_price_basis' => $sellingPrice,
                'tax_amount' => $taxAmount,
                'line_total_with_tax' => $lineTotalWithTax,
            ];

            $taxableBaseTotal += $sellingPrice;
            $taxTotal += $taxAmount;
        }

        $taxableBaseTotal = round($taxableBaseTotal, 2);
        $taxTotal = round($taxTotal, 2);
        $preRoundTotal = round($taxableBaseTotal + $taxTotal, 2);

        // Final payable fractional Rupiah rounds upward to the next whole
        // Rupiah — FINALIZED-DECISIONS.md §3.
        $roundedTotal = ceil($preRoundTotal);
        $roundingAdjustment = round($roundedTotal - $preRoundTotal, 2);

        return [
            'lines' => $result,
            'subtotal' => $subtotal,
            'discount_total' => $documentDiscountAmount,
            'taxable_base_total' => $taxableBaseTotal,
            'tax_total' => $taxTotal,
            'pre_round_total' => $preRoundTotal,
            'rounding_adjustment' => $roundingAdjustment,
            'total' => $roundedTotal,
        ];
    }

    /**
     * Allocates $amount proportionally across $values, then fixes the
     * inevitable rounding remainder by handing the leftover cents to the
     * lines with the largest fractional remainder first — a deterministic
     * policy so repeated calls against the same inputs always split the
     * discount identically.
     *
     * @param  array<int, float>  $values
     * @return array<int, float>
     */
    private function allocateLargestRemainder(array $values, float $amount): array
    {
        $total = array_sum($values);

        if ($total <= 0 || $amount <= 0) {
            return array_fill(0, count($values), 0.0);
        }

        $rawShares = [];
        $flooredShares = [];
        $flooredSum = 0;

        foreach ($values as $index => $value) {
            $raw = $amount * ($value / $total) * 100; // work in integer cents
            $rawShares[$index] = $raw;
            $flooredShares[$index] = (int) floor($raw);
            $flooredSum += $flooredShares[$index];
        }

        $remainderCents = (int) round($amount * 100) - $flooredSum;

        // Largest fractional remainder gets the leftover cents first.
        $order = array_keys($rawShares);
        usort($order, fn ($a, $b) => ($rawShares[$b] - $flooredShares[$b]) <=> ($rawShares[$a] - $flooredShares[$a]));

        foreach ($order as $index) {
            if ($remainderCents <= 0) {
                break;
            }

            $flooredShares[$index]++;
            $remainderCents--;
        }

        $shares = [];

        foreach ($values as $index => $value) {
            $shares[$index] = round($flooredShares[$index] / 100, 2);
        }

        return $shares;
    }
}
