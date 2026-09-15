<?php

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\TaxRate;
use App\Models\Vendor;
use App\Services\ExpenseTotalsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ported from tests/Feature/Filament/ExpensesTest.php during the
 * Filament-removal Phase B — this test exercises
 * App\Services\ExpenseTotalsCalculator directly (no Filament UI involved)
 * and had no other coverage, so it's kept here rather than dropped along
 * with the rest of that file's genuinely Filament-UI-only tests.
 */
class ExpenseTotalsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_totals_recalculate_from_amount_and_taxes(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $vendor = Vendor::create(['company_id' => $company->id, 'name' => 'ACME Supplies']);
        $category = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Office']);
        $taxRate = TaxRate::create(['company_id' => $company->id, 'name' => 'VAT', 'rate' => 10]);

        $expense = $company->expenses()->create([
            'vendor_id' => $vendor->id,
            'expense_category_id' => $category->id,
            'subtotal' => 100,
        ]);

        app(ExpenseTotalsCalculator::class)->syncTaxes($expense, [$taxRate->id]);
        app(ExpenseTotalsCalculator::class)->recalculate($expense->fresh());

        $expense->refresh();

        $this->assertSame('10.00', (string) $expense->tax_total);
        $this->assertSame('110.00', (string) $expense->total);
    }
}
