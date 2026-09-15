<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackReports;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyTaxSetting;
use App\Models\Invoice;
use App\Models\JobCostAllocation;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\TaxRecap;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Models\VendorPurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the TALL-stack Reports page (App\Livewire\TallStackReports) —
 * same shape as tests/Feature/Filament/JobMarginReportTest.php, proving
 * the job-margin section reuses App\Filament\Widgets\JobMarginReport's
 * exact computation, and that the Tax Reports section both lists real
 * App\Models\TaxRecap rows and renders the tax-disabled empty state for a
 * non-taxable company.
 */
class TallStackReportsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);

        $this->actingAs($user);
    }

    private function makeSalesOrder(Company $company, Client $client, string $number): SalesOrder
    {
        $quotation = Quotation::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => "QUO-{$number}",
            'status' => 'accepted',
        ]);

        return SalesOrder::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'number' => $number,
            'status' => 'in_progress',
        ]);
    }

    private function makeVendorBillItem(Company $company, float $lineTotal): VendorBillItem
    {
        $vendor = Vendor::create(['company_id' => $company->id, 'name' => 'Vendor '.uniqid()]);

        $po = VendorPurchaseOrder::create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'number' => 'VPO-'.random_int(100000, 999999),
            'total' => $lineTotal,
        ]);

        $bill = VendorBill::create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'vendor_purchase_order_id' => $po->id,
            'number' => 'VBL-'.random_int(100000, 999999),
            'total' => $lineTotal,
        ]);

        return VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'title' => 'Cabling',
            'quantity' => 1,
            'unit_cost' => $lineTotal,
            'net_amount' => $lineTotal,
            'tax_amount' => 0,
            'line_total' => $lineTotal,
        ]);
    }

    public function test_job_margin_section_matches_job_margin_report_widget_computation(): void
    {
        $job = $this->makeSalesOrder($this->company, $this->client, 'SO-0001');

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'sales_order_id' => $job->id,
            'type' => 'invoice',
            'status' => 'issued',
            'number' => 'INV-0001',
        ]);
        $invoice->forceFill(['total' => 11_000_000, 'tax_total' => 1_000_000, 'balance' => 11_000_000])->save();

        // Excluded — void invoices never count toward sales value.
        $voidInvoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'sales_order_id' => $job->id,
            'type' => 'invoice',
            'status' => 'void',
            'number' => 'INV-0002',
        ]);
        $voidInvoice->forceFill(['total' => 5_000_000, 'tax_total' => 0, 'balance' => 0])->save();

        $itemA = $this->makeVendorBillItem($this->company, 4_000_000);
        JobCostAllocation::create([
            'vendor_bill_item_id' => $itemA->id,
            'sales_order_id' => $job->id,
            'method' => 'amount',
            'amount' => 4_000_000,
        ]);

        $itemB = $this->makeVendorBillItem($this->company, 3_000_000);
        JobCostAllocation::create([
            'vendor_bill_item_id' => $itemB->id,
            'sales_order_id' => $job->id,
            'method' => 'amount',
            'amount' => 2_000_000,
        ]);

        Livewire::test(TallStackReports::class, ['company' => $this->company])
            ->assertSee('SO-0001')
            // Sales value 10,000,000 (11M - 1M tax); allocated gross cost
            // 6,000,000 (4M + 2M); margin 4,000,000; unallocated
            // purchasing cost 1,000,000 (3M item B minus its 2M
            // allocation) — identical to JobMarginReportTest's own
            // assertions, proving this page reuses the same computation.
            ->assertSeeHtml('US$10.000.000')
            ->assertSeeHtml('US$6.000.000')
            ->assertSeeHtml('US$4.000.000')
            ->assertSeeHtml('US$1.000.000');
    }

    public function test_job_margin_section_is_company_scoped(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co', 'currency_code' => 'USD']);
        $otherClient = Client::create(['company_id' => $otherCompany->id, 'name' => 'Other Client']);
        $this->makeSalesOrder($otherCompany, $otherClient, 'SO-OTHER');

        $this->makeSalesOrder($this->company, $this->client, 'SO-0002');

        Livewire::test(TallStackReports::class, ['company' => $this->company])
            ->assertSee('SO-0002')
            ->assertDontSee('SO-OTHER');
    }

    public function test_tax_reports_section_lists_real_tax_recap_rows_for_a_taxable_company(): void
    {
        CompanyTaxSetting::create(['company_id' => $this->company->id, 'tax_enabled' => true]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'issued',
            'number' => 'INV-0003',
        ]);
        $invoice->forceFill(['total' => 11_000_000, 'tax_total' => 1_000_000, 'balance' => 11_000_000])->save();

        $recap = TaxRecap::create([
            'invoice_id' => $invoice->id,
            'number' => 'TAX-0001',
            'reporting_period' => '2026-09',
            'manual_entry_status' => 'pending',
        ]);

        Livewire::test(TallStackReports::class, ['company' => $this->company])
            ->assertSee('TAX-0001')
            ->assertSee('Pending')
            ->assertDontSee('Tax is disabled for this company');

        $recap->update(['manual_entry_status' => 'filed', 'filing_date' => now()]);

        Livewire::test(TallStackReports::class, ['company' => $this->company])
            ->assertSee('Filed');

        $recap->update(['adjusted_at' => now(), 'adjustment_reason' => 'Correction']);

        Livewire::test(TallStackReports::class, ['company' => $this->company])
            ->assertSee('Adjusted');
    }

    public function test_tax_reports_section_shows_a_disabled_state_for_a_non_taxable_company(): void
    {
        CompanyTaxSetting::create(['company_id' => $this->company->id, 'tax_enabled' => false]);

        Livewire::test(TallStackReports::class, ['company' => $this->company])
            ->assertSee('Tax is disabled for this company');
    }

    public function test_a_tax_recap_from_another_company_is_never_listed(): void
    {
        CompanyTaxSetting::create(['company_id' => $this->company->id, 'tax_enabled' => true]);

        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co-2', 'currency_code' => 'USD']);
        $otherClient = Client::create(['company_id' => $otherCompany->id, 'name' => 'Other Client']);
        $otherInvoice = Invoice::create([
            'company_id' => $otherCompany->id,
            'client_id' => $otherClient->id,
            'type' => 'invoice',
            'status' => 'issued',
            'number' => 'INV-OTHER',
        ]);
        $otherInvoice->forceFill(['total' => 1_000_000, 'tax_total' => 100_000, 'balance' => 1_000_000])->save();
        TaxRecap::create([
            'invoice_id' => $otherInvoice->id,
            'number' => 'TAX-OTHER',
            'reporting_period' => '2026-09',
            'manual_entry_status' => 'pending',
        ]);

        Livewire::test(TallStackReports::class, ['company' => $this->company])
            ->assertDontSee('TAX-OTHER');
    }

    public function test_mount_aborts_for_a_user_without_access_to_the_company(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);

        Livewire::test(TallStackReports::class, ['company' => $otherCompany])
            ->assertStatus(403);
    }
}
