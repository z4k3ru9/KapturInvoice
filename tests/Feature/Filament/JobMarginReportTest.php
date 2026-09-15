<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\JobMarginReport;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JobCostAllocation;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Models\VendorPurchaseOrder;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Job margin clearly distinguishes allocated gross cost from unallocated
 * purchasing cost" — docs/rebuild/specs/05-procurement-and-delivery/
 * Specs.md acceptance criteria, deferred to Phase 06. "Dashboard and
 * reports are company-scoped and paginated/cached." —
 * docs/rebuild/specs/06-documents-portal-reporting/Specs.md.
 */
class JobMarginReportTest extends TestCase
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
        Filament::setTenant($this->company);
        app(Tenancy::class)->set($this->company);
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

    /** A vendor bill item costing $lineTotal, tied to a fresh vendor/PO/bill. */
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

    public function test_widget_computes_sales_value_allocated_cost_margin_and_unallocated_cost(): void
    {
        $job = $this->makeSalesOrder($this->company, $this->client, 'SO-0001');

        // Counted: 11,000,000 total, 1,000,000 tax -> 10,000,000 sales value.
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

        // Fully allocated item: 4,000,000 all allocated to this job.
        $itemA = $this->makeVendorBillItem($this->company, 4_000_000);
        JobCostAllocation::create([
            'vendor_bill_item_id' => $itemA->id,
            'sales_order_id' => $job->id,
            'method' => 'amount',
            'amount' => 4_000_000,
        ]);

        // Partially allocated item: 3,000,000 total, only 2,000,000 allocated
        // to this job -> 1,000,000 unallocated remainder.
        $itemB = $this->makeVendorBillItem($this->company, 3_000_000);
        JobCostAllocation::create([
            'vendor_bill_item_id' => $itemB->id,
            'sales_order_id' => $job->id,
            'method' => 'amount',
            'amount' => 2_000_000,
        ]);

        Livewire::test(JobMarginReport::class)
            ->assertCanSeeTableRecords([$job])
            ->assertTableColumnStateSet('sales_value', 10_000_000.0, record: $job)
            ->assertTableColumnStateSet('allocated_cost', 6_000_000.0, record: $job)
            ->assertTableColumnStateSet('margin', 4_000_000.0, record: $job)
            ->assertTableColumnStateSet('unallocated_purchasing_cost', 1_000_000.0, record: $job);
    }

    public function test_widget_is_company_scoped(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co', 'currency_code' => 'USD']);
        $otherClient = Client::create(['company_id' => $otherCompany->id, 'name' => 'Other Client']);
        $otherJob = $this->makeSalesOrder($otherCompany, $otherClient, 'SO-OTHER');

        $ownJob = $this->makeSalesOrder($this->company, $this->client, 'SO-0002');

        Livewire::test(JobMarginReport::class)
            ->assertCanSeeTableRecords([$ownJob])
            ->assertCanNotSeeTableRecords([$otherJob]);
    }
}
