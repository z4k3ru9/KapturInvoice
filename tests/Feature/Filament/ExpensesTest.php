<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\Expenses\Pages\ViewExpense;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ExpenseTotalsCalculator;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
        app(Tenancy::class)->set($this->company);
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

    public function test_vendor_expense_and_expense_category_resources_are_in_the_procurement_nav_group(): void
    {
        foreach ([VendorResource::class, ExpenseResource::class, ExpenseCategoryResource::class] as $resource) {
            $this->assertSame('Procurement', $resource::getNavigationGroup());
        }
    }

    public function test_expenses_table_can_be_filtered_by_vendor_and_sorts_by_expense_date_by_default(): void
    {
        $vendorA = Vendor::create(['company_id' => $this->company->id, 'name' => 'Vendor A']);
        $vendorB = Vendor::create(['company_id' => $this->company->id, 'name' => 'Vendor B']);
        $category = ExpenseCategory::create(['company_id' => $this->company->id, 'name' => 'Office']);

        $older = $this->company->expenses()->create([
            'vendor_id' => $vendorA->id,
            'expense_category_id' => $category->id,
            'expense_date' => now()->subDays(5),
            'subtotal' => 100,
        ]);
        $newer = $this->company->expenses()->create([
            'vendor_id' => $vendorB->id,
            'expense_category_id' => $category->id,
            'expense_date' => now(),
            'subtotal' => 200,
        ]);

        Livewire::test(ListExpenses::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
            ->filterTable('vendor_id', $vendorA->id)
            ->assertCanSeeTableRecords([$older])
            ->assertCanNotSeeTableRecords([$newer]);
    }

    public function test_expense_infolist_groups_amounts_and_record_metadata_into_sections(): void
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'ACME Supplies']);
        $category = ExpenseCategory::create(['company_id' => $this->company->id, 'name' => 'Office']);

        $expense = $this->company->expenses()->create([
            'vendor_id' => $vendor->id,
            'expense_category_id' => $category->id,
            'subtotal' => 100,
        ]);

        Livewire::test(ViewExpense::class, ['record' => $expense->getRouteKey()])
            ->assertSchemaComponentExists('vendor.name')
            ->assertSchemaComponentExists('subtotal')
            ->assertSchemaComponentExists('legacy_expense_id');
    }
}
