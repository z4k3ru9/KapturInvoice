<?php

namespace Tests\Feature\Procurement;

use App\Models\Company;
use App\Models\Vendor;
use App\Models\VendorPurchaseOrder;
use App\Services\Procurement\VendorPurchaseOrderTotalsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GitHub issue #18: a vendor trade/volume discount on a Vendor PO line —
 * mirrors App\Services\InvoiceTotalsCalculator's own per-item discount step
 * (percentage vs. flat amount, applied before the document total is summed).
 */
class VendorPurchaseOrderItemDiscountTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $this->vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Test Vendor']);
    }

    private function makePurchaseOrder(): VendorPurchaseOrder
    {
        return VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'number' => 'ACM-VPO-'.random_int(100000, 999999),
        ])->fresh();
    }

    public function test_flat_amount_discount_reduces_the_line_before_the_po_total(): void
    {
        $po = $this->makePurchaseOrder();

        $po->items()->create([
            'title' => 'Cabling',
            'quantity' => 10,
            'unit_cost' => 100,
            'discount' => 50,
            'discount_is_percentage' => false,
        ]);

        app(VendorPurchaseOrderTotalsCalculator::class)->recalculate($po->fresh());

        $fresh = $po->fresh();
        // 10 * 100 = 1,000 gross, less a flat 50 discount = 950.
        $this->assertSame('950.00', (string) $fresh->items->first()->line_total);
        $this->assertSame('950.00', (string) $fresh->total);
    }

    public function test_percentage_discount_reduces_the_line_before_the_po_total(): void
    {
        $po = $this->makePurchaseOrder();

        $po->items()->create([
            'title' => 'Cabling',
            'quantity' => 10,
            'unit_cost' => 100,
            'discount' => 10,
            'discount_is_percentage' => true,
        ]);

        app(VendorPurchaseOrderTotalsCalculator::class)->recalculate($po->fresh());

        $fresh = $po->fresh();
        // 10 * 100 = 1,000 gross, less a 10% discount = 900.
        $this->assertSame('900.00', (string) $fresh->items->first()->line_total);
        $this->assertSame('900.00', (string) $fresh->total);
    }

    public function test_a_discounted_line_alongside_a_full_price_line_sums_correctly(): void
    {
        $po = $this->makePurchaseOrder();

        $po->items()->create([
            'title' => 'Discounted cabling',
            'quantity' => 10,
            'unit_cost' => 100,
            'discount' => 10,
            'discount_is_percentage' => true,
        ]);
        $po->items()->create([
            'title' => 'Full-price mounting hardware',
            'quantity' => 5,
            'unit_cost' => 20,
        ]);

        app(VendorPurchaseOrderTotalsCalculator::class)->recalculate($po->fresh());

        // 900 (discounted) + 100 (undiscounted) = 1,000.
        $this->assertSame('1000.00', (string) $po->fresh()->total);
    }
}
