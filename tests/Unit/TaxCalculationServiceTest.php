<?php

namespace Tests\Unit;

use App\Enums\PricingMode;
use App\Enums\TaxCategory;
use App\Models\CompanyTaxSetting;
use App\Services\Tax\TaxCalculationService;
use Tests\TestCase;

/**
 * Pure-logic tests for the calculation engine behind
 * docs/rebuild/specs/04-billing-and-receivables/Specs.md — no database
 * writes, so this stays a Unit test (see docs/testing-coverage.md
 * "Approach"). CompanyTaxSetting is instantiated in memory (never saved)
 * purely as a typed carrier for tax_enabled/standard_tax_rate/dpp factor.
 */
class TaxCalculationServiceTest extends TestCase
{
    private function companyBTaxSetting(): CompanyTaxSetting
    {
        return new CompanyTaxSetting([
            'tax_enabled' => true,
            'standard_tax_rate' => 12.00,
            'dpp_factor_numerator' => 11,
            'dpp_factor_denominator' => 12,
        ]);
    }

    private function line(float $unitCost, float $quantity = 1, float $discount = 0, bool $discountIsPercentage = false, ?TaxCategory $taxCategory = null): array
    {
        return [
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'discount' => $discount,
            'discount_is_percentage' => $discountIsPercentage,
            'tax_category' => $taxCategory ?? TaxCategory::StandardTaxable,
        ];
    }

    public function test_rp10_000_000_exclusive_example(): void
    {
        $service = new TaxCalculationService;

        $result = $service->calculate(
            [$this->line(10_000_000)],
            PricingMode::Exclusive,
            $this->companyBTaxSetting(),
        );

        // 12% PPN against the 11/12 DPP Nilai Lain factor collapses to an
        // exact 11% add-on: 10,000,000 * 0.11 = 1,100,000.
        $this->assertSame(10_000_000.0, $result['subtotal']);
        $this->assertSame(1_100_000.0, $result['tax_total']);
        $this->assertSame(11_100_000.0, $result['total']);
        $this->assertSame(0.0, $result['rounding_adjustment']);
    }

    public function test_rp11_100_000_inclusive_example(): void
    {
        $service = new TaxCalculationService;

        $result = $service->calculate(
            [$this->line(11_100_000)],
            PricingMode::Inclusive,
            $this->companyBTaxSetting(),
        );

        // The same transaction viewed the other way round: an inclusive
        // price of 11,100,000 backs out to a 10,000,000 selling price and
        // 1,100,000 tax — proving the two pricing modes are consistent.
        $this->assertSame(1_100_000.0, $result['tax_total']);
        $this->assertSame(11_100_000.0, $result['total']);
        $this->assertSame(10_000_000.0, $result['taxable_base_total']);
    }

    public function test_non_tax_company_behavior(): void
    {
        $service = new TaxCalculationService;

        $result = $service->calculate(
            [$this->line(5_000_000)],
            PricingMode::Exclusive,
            new CompanyTaxSetting(['tax_enabled' => false]),
        );

        $this->assertSame(0.0, $result['tax_total']);
        $this->assertSame(5_000_000.0, $result['total']);
    }

    public function test_non_tax_company_behavior_with_no_tax_setting_at_all(): void
    {
        $service = new TaxCalculationService;

        $result = $service->calculate([$this->line(1_000_000)], PricingMode::Exclusive, null);

        $this->assertSame(0.0, $result['tax_total']);
        $this->assertSame(1_000_000.0, $result['total']);
    }

    public function test_a_non_taxable_line_is_never_taxed_even_for_a_tax_enabled_company(): void
    {
        $service = new TaxCalculationService;

        $result = $service->calculate(
            [$this->line(1_000_000, taxCategory: TaxCategory::NonTaxable)],
            PricingMode::Exclusive,
            $this->companyBTaxSetting(),
        );

        $this->assertSame(0.0, $result['tax_total']);
        $this->assertSame(1_000_000.0, $result['total']);
    }

    public function test_line_percentage_discount_reduces_the_taxable_base_before_tax(): void
    {
        $service = new TaxCalculationService;

        // 1,000,000 less a 10% line discount = 900,000 taxable base.
        $result = $service->calculate(
            [$this->line(1_000_000, discount: 10, discountIsPercentage: true)],
            PricingMode::Exclusive,
            $this->companyBTaxSetting(),
        );

        $this->assertSame(900_000.0, $result['subtotal']);
        $this->assertSame(99_000.0, $result['tax_total']); // 900,000 * 11%
        $this->assertSame(999_000.0, $result['total']);
    }

    public function test_global_nominal_discount_reduces_the_taxable_base_before_tax(): void
    {
        $service = new TaxCalculationService;

        $result = $service->calculate(
            [$this->line(1_000_000), $this->line(1_000_000)],
            PricingMode::Exclusive,
            $this->companyBTaxSetting(),
            documentDiscount: 200_000,
        );

        $this->assertSame(2_000_000.0, $result['subtotal']);
        $this->assertSame(200_000.0, $result['discount_total']);
        $this->assertSame(1_800_000.0, $result['taxable_base_total']);
        $this->assertSame(198_000.0, $result['tax_total']); // 1,800,000 * 11%
        $this->assertSame(1_998_000.0, $result['total']);
    }

    public function test_global_percentage_discount_allocates_proportionally_across_uneven_lines(): void
    {
        $service = new TaxCalculationService;

        // A 10% global discount on a 1,000,000 subtotal is 100,000, split
        // proportionally across a 700,000/300,000 line split: 70,000/30,000.
        $result = $service->calculate(
            [$this->line(700_000, taxCategory: TaxCategory::NonTaxable), $this->line(300_000, taxCategory: TaxCategory::NonTaxable)],
            PricingMode::Exclusive,
            new CompanyTaxSetting(['tax_enabled' => false]),
            documentDiscount: 10,
            documentDiscountIsPercentage: true,
        );

        $this->assertSame(70_000.0, $result['lines'][0]['discount_share']);
        $this->assertSame(30_000.0, $result['lines'][1]['discount_share']);
        $this->assertSame(900_000.0, $result['total']);
    }

    public function test_ceiling_rounding_rounds_the_final_total_upward_to_a_whole_rupiah(): void
    {
        $service = new TaxCalculationService;

        // 100,000 * 11% = 11,000 tax exactly — pick a base that produces a
        // fractional pre-round total instead.
        $result = $service->calculate(
            [$this->line(100_000.01)],
            PricingMode::Exclusive,
            $this->companyBTaxSetting(),
        );

        $this->assertGreaterThan(0.0, $result['pre_round_total'] - floor($result['pre_round_total']));
        $this->assertSame(ceil($result['pre_round_total']), $result['total']);
        $this->assertSame(round($result['total'] - $result['pre_round_total'], 2), $result['rounding_adjustment']);
        $this->assertGreaterThanOrEqual(0.0, $result['rounding_adjustment']);
    }
}
