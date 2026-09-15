<?php

namespace Tests\Feature\Procurement;

use App\Actions\Procurement\ApproveVendorBill;
use App\Actions\Procurement\ApproveVendorPurchaseOrder;
use App\Actions\Procurement\RecordVendorPayment;
use App\Actions\Procurement\SubmitVendorBill;
use App\Actions\Procurement\VerifyVendorPayment;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorPurchaseOrder;
use App\Models\VendorPurchaseOrderItem;
use App\Services\Procurement\VendorBillTotalsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * GitHub issue #18: a vendor trade/volume discount on a Vendor Bill line —
 * mirrors App\Services\InvoiceTotalsCalculator's own per-item discount step
 * (percentage vs. flat amount). The discount reduces this line's own
 * `net_amount` base only — `tax_amount` (the vendor's own charged,
 * nonrecoverable tax, FINALIZED-DECISIONS.md §3) is never touched by it.
 */
class VendorBillItemDiscountTest extends TestCase
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

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role]);

        return $user;
    }

    private function makeApprovedPurchaseOrder(float $total): VendorPurchaseOrder
    {
        $po = VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'number' => 'ACM-VPO-'.random_int(100000, 999999),
        ]);

        VendorPurchaseOrderItem::create([
            'vendor_purchase_order_id' => $po->id,
            'title' => 'Cabling',
            'quantity' => 1,
            'unit_cost' => $total,
            'line_total' => $total,
        ]);

        $po->forceFill(['total' => $total])->save();
        app(ApproveVendorPurchaseOrder::class)->approve($po->fresh());

        return $po->fresh();
    }

    private function makeBill(VendorPurchaseOrder $po): VendorBill
    {
        return VendorBill::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'vendor_purchase_order_id' => $po->id,
            'number' => 'ACM-VBL-'.random_int(100000, 999999),
        ])->fresh();
    }

    public function test_flat_amount_discount_reduces_net_before_it_is_combined_with_tax(): void
    {
        $po = $this->makeApprovedPurchaseOrder(1000000);
        $bill = $this->makeBill($po);

        $bill->items()->create([
            'title' => 'Cabling',
            'quantity' => 10,
            'unit_cost' => 100000,
            'discount' => 50000,
            'discount_is_percentage' => false,
            'net_amount' => 1000000,
            'tax_amount' => 110000,
        ]);

        app(VendorBillTotalsCalculator::class)->recalculate($bill->fresh());

        $fresh = $bill->fresh();
        // Net 1,000,000 less a flat 50,000 discount = 950,000, plus tax
        // 110,000 (untouched) = 1,060,000.
        $this->assertSame('1060000.00', (string) $fresh->items->first()->line_total);
        $this->assertSame('1060000.00', (string) $fresh->total);
    }

    public function test_percentage_discount_reduces_net_before_it_is_combined_with_tax(): void
    {
        $po = $this->makeApprovedPurchaseOrder(1000000);
        $bill = $this->makeBill($po);

        $bill->items()->create([
            'title' => 'Cabling',
            'quantity' => 10,
            'unit_cost' => 100000,
            'discount' => 10,
            'discount_is_percentage' => true,
            'net_amount' => 1000000,
            'tax_amount' => 110000,
        ]);

        app(VendorBillTotalsCalculator::class)->recalculate($bill->fresh());

        $fresh = $bill->fresh();
        // Net 1,000,000 less a 10% discount = 900,000, plus tax 110,000
        // (untouched — no input-tax-credit recomputation) = 1,010,000.
        $this->assertSame('1010000.00', (string) $fresh->items->first()->line_total);
        $this->assertSame('1010000.00', (string) $fresh->total);
    }

    /**
     * Live check per the issue: a Vendor PO's `total` is immutable once
     * Approved, and `paymentCeiling()` reads only that frozen total plus
     * approved variances — never a bill's own (possibly discounted)
     * total. A discounted bill line must not let a payment silently slip
     * past, nor unfairly trip, that ceiling.
     */
    public function test_discounted_bill_still_respects_the_pos_payment_ceiling(): void
    {
        $owner = $this->userWithRole('owner');
        $po = $this->makeApprovedPurchaseOrder(1000000);

        $bill = $this->makeBill($po);
        $bill->items()->create([
            'title' => 'Cabling',
            'quantity' => 10,
            'unit_cost' => 100000,
            'discount' => 10,
            'discount_is_percentage' => true,
            'net_amount' => 1000000,
            'tax_amount' => 0,
        ]);
        app(VendorBillTotalsCalculator::class)->recalculate($bill->fresh());

        // Discounted bill total (900,000) stays under the PO's frozen
        // total/ceiling (1,000,000) — the PO's own total is untouched by
        // the bill's discount.
        $this->assertSame('1000000.00', $po->fresh()->total);
        $this->assertSame('900000.00', $bill->fresh()->total);
        $this->assertSame(1000000.0, $po->fresh()->paymentCeiling());

        app(SubmitVendorBill::class)->submit($bill->fresh());
        app(ApproveVendorBill::class)->approve($bill->fresh(), $owner);

        $payment = app(RecordVendorPayment::class)->record($bill->fresh(), [
            'amount' => 900000,
            'payment_date' => now(),
            'proof_path' => 'vendor-payment-proofs/proof.pdf',
        ]);
        app(VerifyVendorPayment::class)->verify($payment, $owner);

        $this->assertSame('0.00', $bill->fresh()->balance);

        // A second, undiscounted bill against the same PO must still be
        // blocked once the combined total would exceed the PO's own
        // immutable ceiling — the first bill's discount doesn't quietly
        // "free up" ceiling room it was never given.
        $secondBill = $this->makeBill($po);
        $secondBill->items()->create([
            'title' => 'Extra cabling',
            'quantity' => 1,
            'unit_cost' => 200000,
            'net_amount' => 200000,
            'tax_amount' => 0,
        ]);
        app(VendorBillTotalsCalculator::class)->recalculate($secondBill->fresh());
        app(SubmitVendorBill::class)->submit($secondBill->fresh());
        app(ApproveVendorBill::class)->approve($secondBill->fresh(), $owner);

        try {
            app(RecordVendorPayment::class)->record($secondBill->fresh(), [
                'amount' => 200000,
                'payment_date' => now(),
                'proof_path' => 'vendor-payment-proofs/proof.pdf',
            ]);
            $this->fail('Expected RuntimeException for payment exceeding the PO payment ceiling.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('payment ceiling', $e->getMessage());
        }
    }
}
