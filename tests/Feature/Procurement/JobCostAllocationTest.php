<?php

namespace Tests\Feature\Procurement;

use App\Actions\Procurement\AllocateJobCost;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Models\VendorPurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Required tests from docs/rebuild/specs/05-procurement-and-delivery/Specs.md:
 * "Shared purchase split across two jobs", "Unallocated cost visible",
 * "Allocation over source amount rejected".
 */
class JobCostAllocationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private VendorBillItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Test Vendor']);

        $po = VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $vendor->id,
            'number' => 'ACM-VPO-0001',
            'total' => 1000,
        ]);

        $bill = VendorBill::create([
            'company_id' => $this->company->id,
            'vendor_id' => $vendor->id,
            'vendor_purchase_order_id' => $po->id,
            'number' => 'ACM-VBL-0001',
            'total' => 1000,
        ]);

        $this->item = VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'title' => 'Cabling materials',
            'quantity' => 1,
            'unit_cost' => 1000,
            'net_amount' => 1000,
            'tax_amount' => 0,
            'line_total' => 1000,
        ]);
    }

    private function makeJob(): SalesOrder
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => 'ACM-QUO-'.random_int(100000, 999999),
        ]);

        return SalesOrder::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'number' => 'ACM-SO-'.random_int(100000, 999999),
            'approved_value' => 1000,
        ]);
    }

    public function test_a_shared_vendor_bill_item_can_be_split_across_two_jobs(): void
    {
        $jobA = $this->makeJob();
        $jobB = $this->makeJob();

        $allocationA = app(AllocateJobCost::class)->allocate($this->item, $jobA, 600.00);
        $allocationB = app(AllocateJobCost::class)->allocate($this->item->fresh(), $jobB, 400.00);

        $this->assertSame('600.00', $allocationA->amount);
        $this->assertSame('400.00', $allocationB->amount);
        $this->assertSame($jobA->id, $allocationA->sales_order_id);
        $this->assertSame($jobB->id, $allocationB->sales_order_id);
        $this->assertSame(0.0, $this->item->fresh()->unallocatedAmount());
    }

    public function test_the_unallocated_remainder_is_visible_after_a_partial_allocation(): void
    {
        $job = $this->makeJob();

        app(AllocateJobCost::class)->allocate($this->item, $job, 300.00);

        $this->assertSame(700.0, $this->item->fresh()->unallocatedAmount());
    }

    public function test_an_allocation_exceeding_the_remaining_unallocated_amount_is_rejected(): void
    {
        $jobA = $this->makeJob();
        $jobB = $this->makeJob();

        app(AllocateJobCost::class)->allocate($this->item, $jobA, 900.00);

        $this->expectException(RuntimeException::class);

        app(AllocateJobCost::class)->allocate($this->item->fresh(), $jobB, 200.00);
    }

    public function test_a_zero_or_negative_allocation_amount_is_rejected(): void
    {
        $job = $this->makeJob();

        $this->expectException(RuntimeException::class);

        app(AllocateJobCost::class)->allocate($this->item, $job, 0.0);
    }
}
