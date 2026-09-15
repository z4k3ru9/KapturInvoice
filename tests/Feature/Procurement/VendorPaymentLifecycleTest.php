<?php

namespace Tests\Feature\Procurement;

use App\Actions\Procurement\AmendVendorPayment;
use App\Actions\Procurement\ApproveVendorBill;
use App\Actions\Procurement\ApproveVendorPurchaseOrder;
use App\Actions\Procurement\IssueVendorPaymentReceipt;
use App\Actions\Procurement\RecordVendorPayment;
use App\Actions\Procurement\ReverseVendorPayment;
use App\Actions\Procurement\SubmitVendorBill;
use App\Actions\Procurement\VerifyVendorPayment;
use App\Enums\VendorBillStatus;
use App\Enums\VendorPaymentStatus;
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
 * The parallel immutable vendor-payment event model required by
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §7 — proof before
 * verification, one Vendor Payment Receipt per verified event, and
 * linked amendment/reversal for corrections, mirroring
 * tests/Feature/Filament/PaymentReceivablesActionsTest.php's coverage of
 * the customer-side equivalent.
 */
class VendorPaymentLifecycleTest extends TestCase
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

    private function makeApprovedBill(float $total): VendorBill
    {
        $owner = $this->userWithRole('owner');

        $po = VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'number' => 'ACM-VPO-'.random_int(100000, 999999),
        ]);
        VendorPurchaseOrderItem::create([
            'vendor_purchase_order_id' => $po->id,
            'title' => 'Cabling', 'quantity' => 1, 'unit_cost' => $total, 'line_total' => $total,
        ]);
        $po->forceFill(['total' => $total])->save();
        app(ApproveVendorPurchaseOrder::class)->approve($po->fresh());

        $bill = VendorBill::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'vendor_purchase_order_id' => $po->id,
            'number' => 'ACM-VBL-'.random_int(100000, 999999),
        ]);
        VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'title' => 'Cabling', 'quantity' => 1, 'unit_cost' => $total,
            'net_amount' => $total, 'tax_amount' => 0, 'line_total' => $total,
        ]);
        $bill->forceFill(['total' => $total])->save();
        app(SubmitVendorBill::class)->submit($bill->fresh());
        app(ApproveVendorBill::class)->approve($bill->fresh(), $owner);

        return $bill->fresh();
    }

    public function test_a_recorded_payment_does_not_count_until_verified(): void
    {
        $bill = $this->makeApprovedBill(1000000);

        $payment = app(RecordVendorPayment::class)->record($bill, [
            'amount' => 1000000,
            'payment_date' => now(),
            'proof_path' => 'vendor-payment-proofs/proof.pdf',
        ]);

        $this->assertSame(VendorPaymentStatus::Pending, $payment->status);
        $this->assertSame('0.00', $bill->fresh()->amount_paid);
        $this->assertSame(VendorBillStatus::Approved, $bill->fresh()->status);
    }

    public function test_verification_requires_proof_of_payment(): void
    {
        $bill = $this->makeApprovedBill(1000000);
        $owner = $this->company->users()->wherePivot('role', 'owner')->first();

        $payment = app(RecordVendorPayment::class)->record($bill, [
            'amount' => 1000000,
            'payment_date' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('proof of payment');

        app(VerifyVendorPayment::class)->verify($payment, $owner);
    }

    public function test_a_cheque_payment_requires_clearance_before_verification(): void
    {
        $bill = $this->makeApprovedBill(1000000);
        $owner = $this->company->users()->wherePivot('role', 'owner')->first();

        $payment = app(RecordVendorPayment::class)->record($bill, [
            'amount' => 1000000,
            'payment_date' => now(),
            'method' => 'cheque',
            'proof_path' => 'vendor-payment-proofs/proof.pdf',
        ]);

        try {
            app(VerifyVendorPayment::class)->verify($payment, $owner);
            $this->fail('Expected RuntimeException for an uncleared cheque.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cheque must clear', $e->getMessage());
        }

        $verified = app(VerifyVendorPayment::class)->verify($payment->fresh(), $owner, now());
        $this->assertSame(VendorPaymentStatus::Verified, $verified->status);
    }

    public function test_only_accountant_or_higher_can_verify_a_vendor_payment(): void
    {
        $bill = $this->makeApprovedBill(1000000);
        $staff = $this->userWithRole('staff');

        $payment = app(RecordVendorPayment::class)->record($bill, [
            'amount' => 1000000,
            'payment_date' => now(),
            'proof_path' => 'vendor-payment-proofs/proof.pdf',
        ]);

        $this->expectException(RuntimeException::class);

        app(VerifyVendorPayment::class)->verify($payment, $staff);
    }

    public function test_exactly_one_receipt_per_verified_payment(): void
    {
        $bill = $this->makeApprovedBill(1000000);
        $owner = $this->company->users()->wherePivot('role', 'owner')->first();

        $payment = app(RecordVendorPayment::class)->record($bill, [
            'amount' => 1000000,
            'payment_date' => now(),
            'proof_path' => 'vendor-payment-proofs/proof.pdf',
        ]);
        app(VerifyVendorPayment::class)->verify($payment, $owner);

        $receipt = app(IssueVendorPaymentReceipt::class)->issue($payment->fresh());
        $this->assertNotNull($receipt->number);
        $this->assertStringContainsString('-VPR-', $receipt->number);

        $this->expectException(RuntimeException::class);
        app(IssueVendorPaymentReceipt::class)->issue($payment->fresh());
    }

    public function test_reversing_a_payment_preserves_its_receipt_and_reduces_the_bills_paid_amount(): void
    {
        $bill = $this->makeApprovedBill(1000000);
        $owner = $this->company->users()->wherePivot('role', 'owner')->first();

        $payment = app(RecordVendorPayment::class)->record($bill, [
            'amount' => 1000000,
            'payment_date' => now(),
            'proof_path' => 'vendor-payment-proofs/proof.pdf',
        ]);
        app(VerifyVendorPayment::class)->verify($payment, $owner);
        $receipt = app(IssueVendorPaymentReceipt::class)->issue($payment->fresh());

        $this->assertSame(VendorBillStatus::Paid, $bill->fresh()->status);

        app(ReverseVendorPayment::class)->reverse($payment->fresh(), 'Bank recall', $owner);

        // Mirrors App\Actions\Receivables\ReverseCustomerPayment: status
        // auto-transition only applies while the bill is in a "billable"
        // state (Approved/PartiallyPaid) — once Paid it is deliberately
        // left alone, same as RecalculateInvoiceReceivables; only the
        // derived amount_paid/balance always reflect the reversal.
        $fresh = $bill->fresh();
        $this->assertSame('0.00', $fresh->amount_paid);
        $this->assertSame('1000000.00', $fresh->balance);

        // The receipt itself is untouched — reversal never mutates history.
        $this->assertNotNull($receipt->fresh());
        $this->assertSame(VendorPaymentStatus::Reversed, $payment->fresh()->status);
    }

    public function test_amending_a_receipted_payment_records_a_linked_amendment_without_changing_the_receipt(): void
    {
        $bill = $this->makeApprovedBill(1000000);
        $owner = $this->company->users()->wherePivot('role', 'owner')->first();

        $payment = app(RecordVendorPayment::class)->record($bill, [
            'amount' => 1000000,
            'payment_date' => now(),
            'proof_path' => 'vendor-payment-proofs/proof.pdf',
        ]);
        app(VerifyVendorPayment::class)->verify($payment, $owner);
        $receipt = app(IssueVendorPaymentReceipt::class)->issue($payment->fresh());
        $originalNumber = $receipt->number;

        $amendment = app(AmendVendorPayment::class)->amend($payment->fresh(), 900000.0, 'Overstated amount', $owner);

        $this->assertSame(['amount' => '1000000.00'], $amendment->changes_before);
        $this->assertSame(['amount' => '900000.00'], $amendment->changes_after);
        $this->assertSame($originalNumber, $receipt->fresh()->number);
        $this->assertSame('900000.00', $bill->fresh()->amount_paid);

        // Codex review finding on PR #4: downloading the receipt PDF
        // used to re-render `vendor_payments.amount` live, so it would
        // have shown 900000 (the amended amount) rather than what was
        // true when the receipt was actually issued.
        $this->assertSame('1000000.00', $receipt->fresh()->snapshot['amount']);
    }

    public function test_amending_a_payment_above_the_po_payment_ceiling_is_rejected(): void
    {
        // Codex review finding on PR #4: AmendVendorPayment used to save
        // any amount unconditionally, bypassing the same PO-wide payment
        // ceiling RecordVendorPayment enforces at record time.
        $bill = $this->makeApprovedBill(1000000);
        $owner = $this->company->users()->wherePivot('role', 'owner')->first();

        $payment = app(RecordVendorPayment::class)->record($bill, [
            'amount' => 1000000,
            'payment_date' => now(),
            'proof_path' => 'vendor-payment-proofs/proof.pdf',
        ]);
        app(VerifyVendorPayment::class)->verify($payment, $owner);
        app(IssueVendorPaymentReceipt::class)->issue($payment->fresh());

        $this->expectException(RuntimeException::class);

        // The PO's total (and so its ceiling, absent any variance) is
        // exactly 1,000,000 — raising this payment to 1,200,000 exceeds it.
        app(AmendVendorPayment::class)->amend($payment->fresh(), 1200000.0, 'Should be rejected', $owner);
    }

    public function test_amending_an_unreceipted_payment_is_rejected(): void
    {
        $bill = $this->makeApprovedBill(1000000);
        $owner = $this->company->users()->wherePivot('role', 'owner')->first();

        $payment = app(RecordVendorPayment::class)->record($bill, [
            'amount' => 1000000,
            'payment_date' => now(),
            'proof_path' => 'vendor-payment-proofs/proof.pdf',
        ]);

        $this->expectException(RuntimeException::class);

        app(AmendVendorPayment::class)->amend($payment, 900000.0, 'reason', $owner);
    }

    public function test_a_pending_payment_still_counts_toward_the_po_payment_ceiling(): void
    {
        $bill = $this->makeApprovedBill(1000000);

        app(RecordVendorPayment::class)->record($bill, [
            'amount' => 700000,
            'payment_date' => now(),
        ]);

        try {
            app(RecordVendorPayment::class)->record($bill->fresh(), [
                'amount' => 400000,
                'payment_date' => now(),
            ]);
            $this->fail('Expected RuntimeException — the still-pending first payment already commits 700000 of the 1000000 ceiling.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('payment ceiling', $e->getMessage());
        }
    }
}
