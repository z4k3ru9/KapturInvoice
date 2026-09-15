<?php

namespace Tests\Feature\Procurement;

use App\Actions\Procurement\ApproveVendorBill;
use App\Actions\Procurement\ApproveVendorPoVariance;
use App\Actions\Procurement\ApproveVendorPurchaseOrder;
use App\Actions\Procurement\IssueVendorPaymentReceipt;
use App\Actions\Procurement\RecordVendorPayment;
use App\Actions\Procurement\SubmitVendorBill;
use App\Actions\Procurement\VerifyVendorPayment;
use App\Enums\VendorBillStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Models\VendorPurchaseOrder;
use App\Models\VendorPurchaseOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Required tests from docs/rebuild/specs/05-procurement-and-delivery/Specs.md
 * (vendor-side): "Vendor PO and bill with full payment", "Partial vendor
 * payment and remaining due date" — plus FINALIZED-DECISIONS.md §4's
 * PO-variance payment-ceiling rule.
 */
class VendorBillWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD',
        ]);
        $this->vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Test Vendor']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role]);

        return $user;
    }

    private function makePurchaseOrder(float $total): VendorPurchaseOrder
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

        return $po->fresh();
    }

    private function makeBill(VendorPurchaseOrder $po, float $total): VendorBill
    {
        $bill = VendorBill::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'vendor_purchase_order_id' => $po->id,
            'number' => 'ACM-VBL-'.random_int(100000, 999999),
        ]);

        VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'title' => 'Cabling',
            'quantity' => 1,
            'unit_cost' => $total,
            'net_amount' => $total,
            'tax_amount' => 0,
            'line_total' => $total,
        ]);

        $bill->forceFill(['total' => $total])->save();

        return $bill->fresh();
    }

    public function test_vendor_po_and_bill_with_full_payment(): void
    {
        $owner = $this->userWithRole('owner');
        $po = $this->makePurchaseOrder(1000000);
        app(ApproveVendorPurchaseOrder::class)->approve($po);

        $bill = $this->makeBill($po->fresh(), 1000000);
        app(SubmitVendorBill::class)->submit($bill);
        app(ApproveVendorBill::class)->approve($bill->fresh(), $owner);

        $payment = app(RecordVendorPayment::class)->record($bill->fresh(), [
            'amount' => 1000000,
            'payment_date' => now(),
            'proof_path' => 'vendor-payment-proofs/proof.pdf',
        ]);

        // Recorded but not yet verified — does not count toward the bill yet.
        $this->assertSame('0.00', $bill->fresh()->amount_paid);

        app(VerifyVendorPayment::class)->verify($payment, $owner);
        $receipt = app(IssueVendorPaymentReceipt::class)->issue($payment->fresh());

        $fresh = $bill->fresh();
        $this->assertSame(VendorBillStatus::Paid, $fresh->status);
        $this->assertSame('0.00', $fresh->balance);
        $this->assertSame('1000000.00', $fresh->amount_paid);
        $this->assertNotNull($receipt->number);
        $this->assertStringContainsString('-VPR-', $receipt->number);
    }

    public function test_partial_vendor_payment_and_remaining_due_date(): void
    {
        $owner = $this->userWithRole('owner');
        $po = $this->makePurchaseOrder(1000000);
        app(ApproveVendorPurchaseOrder::class)->approve($po);

        $bill = $this->makeBill($po->fresh(), 1000000);
        $bill->forceFill(['due_date' => '2026-10-15'])->save();
        app(SubmitVendorBill::class)->submit($bill);
        app(ApproveVendorBill::class)->approve($bill->fresh(), $owner);

        $payment = app(RecordVendorPayment::class)->record($bill->fresh(), [
            'amount' => 400000,
            'payment_date' => now(),
            'proof_path' => 'vendor-payment-proofs/proof.pdf',
        ]);
        app(VerifyVendorPayment::class)->verify($payment, $owner);

        $fresh = $bill->fresh();
        $this->assertSame(VendorBillStatus::PartiallyPaid, $fresh->status);
        $this->assertGreaterThan(0.0, (float) $fresh->balance);
        $this->assertSame('600000.00', $fresh->balance);
        $this->assertSame('2026-10-15', $fresh->due_date->toDateString());
    }

    public function test_vendor_bill_exceeding_po_is_blocked_until_variance_approved(): void
    {
        $owner = $this->userWithRole('owner');
        $po = $this->makePurchaseOrder(1000000);
        app(ApproveVendorPurchaseOrder::class)->approve($po);

        $bill = $this->makeBill($po->fresh(), 1200000);
        app(SubmitVendorBill::class)->submit($bill);
        app(ApproveVendorBill::class)->approve($bill->fresh(), $owner);

        try {
            app(RecordVendorPayment::class)->record($bill->fresh(), [
                'amount' => 1200000,
                'payment_date' => now(),
            ]);
            $this->fail('Expected RuntimeException for payment exceeding PO ceiling.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('payment ceiling', $e->getMessage());
        }

        app(ApproveVendorPoVariance::class)->approve($po->fresh(), $owner, 'Additional cabling required', 200000);

        $payment = app(RecordVendorPayment::class)->record($bill->fresh(), [
            'amount' => 1200000,
            'payment_date' => now(),
            'proof_path' => 'vendor-payment-proofs/proof.pdf',
        ]);
        app(VerifyVendorPayment::class)->verify($payment, $owner);

        $this->assertSame('1200000.00', $payment->amount);
        $this->assertSame(VendorBillStatus::Paid, $bill->fresh()->status);
    }

    public function test_unauthorized_vendor_bill_approval_is_denied_for_staff(): void
    {
        $staff = $this->userWithRole('staff');
        $po = $this->makePurchaseOrder(1000000);
        app(ApproveVendorPurchaseOrder::class)->approve($po);
        $bill = $this->makeBill($po->fresh(), 1000000);
        app(SubmitVendorBill::class)->submit($bill);

        $this->expectException(RuntimeException::class);

        app(ApproveVendorBill::class)->approve($bill->fresh(), $staff);
    }

    public function test_unauthorized_vendor_bill_approval_is_denied_for_sales(): void
    {
        $sales = $this->userWithRole('sales');
        $po = $this->makePurchaseOrder(1000000);
        app(ApproveVendorPurchaseOrder::class)->approve($po);
        $bill = $this->makeBill($po->fresh(), 1000000);
        app(SubmitVendorBill::class)->submit($bill);

        $this->expectException(RuntimeException::class);

        app(ApproveVendorBill::class)->approve($bill->fresh(), $sales);
    }

    public function test_owner_admin_and_accountant_can_approve_a_vendor_bill(): void
    {
        foreach (['owner', 'admin', 'accountant'] as $role) {
            $user = $this->userWithRole($role);
            $po = $this->makePurchaseOrder(1000000);
            app(ApproveVendorPurchaseOrder::class)->approve($po);
            $bill = $this->makeBill($po->fresh(), 1000000);
            app(SubmitVendorBill::class)->submit($bill);

            $approved = app(ApproveVendorBill::class)->approve($bill->fresh(), $user);

            $this->assertSame(VendorBillStatus::Approved, $approved->status);
        }
    }

    public function test_unauthorized_po_variance_approval_is_denied_for_accountant(): void
    {
        $accountant = $this->userWithRole('accountant');
        $po = $this->makePurchaseOrder(1000000);
        app(ApproveVendorPurchaseOrder::class)->approve($po);

        $this->expectException(RuntimeException::class);

        app(ApproveVendorPoVariance::class)->approve($po->fresh(), $accountant, 'reason', 200000);
    }

    public function test_invalid_vendor_bill_state_transition_is_denied(): void
    {
        $owner = $this->userWithRole('owner');
        $po = $this->makePurchaseOrder(1000000);
        app(ApproveVendorPurchaseOrder::class)->approve($po);
        $bill = $this->makeBill($po->fresh(), 1000000);

        // Draft -> Approved directly, skipping Submitted, is not in
        // VendorBillStatus::Draft->allowedNextStates().
        $this->expectException(RuntimeException::class);

        app(ApproveVendorBill::class)->approve($bill, $owner);
    }

    public function test_original_po_total_is_never_mutated_by_an_approved_variance(): void
    {
        $owner = $this->userWithRole('owner');
        $po = $this->makePurchaseOrder(1000000);
        app(ApproveVendorPurchaseOrder::class)->approve($po);

        app(ApproveVendorPoVariance::class)->approve($po->fresh(), $owner, 'Additional cabling required', 200000);

        $fresh = $po->fresh();
        $this->assertSame('1000000.00', $fresh->total);
        $this->assertSame(1200000.0, $fresh->paymentCeiling());
    }
}
