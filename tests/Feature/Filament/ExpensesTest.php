<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ExpenseTotalsCalculator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    public function test_resource_index_and_create_pages_render(): void
    {
        foreach ([ExpenseCategoryResource::class, VendorResource::class, ExpenseResource::class] as $resource) {
            $this->get($resource::getUrl('index', tenant: $this->company))->assertOk();
        }

        // Only Expense keeps a dedicated Create page (it needs one to host
        // the Documents relation manager after creating) — ExpenseCategory
        // and Vendor dropped theirs in favor of a modal, covered by
        // ModalCreateEditTest instead. See docs/filament-admin-layout-design.md §6.
        $this->get(ExpenseResource::getUrl('create', tenant: $this->company))->assertOk();
    }

    public function test_expense_totals_recalculate_from_amount_and_taxes(): void
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'ACME Supplies']);
        $category = ExpenseCategory::create(['company_id' => $this->company->id, 'name' => 'Office']);
        $taxRate = TaxRate::create(['company_id' => $this->company->id, 'name' => 'VAT', 'rate' => 10]);

        $expense = $this->company->expenses()->create([
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
