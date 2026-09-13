<?php

namespace Tests\Feature\Receivables;

use App\Actions\Receivables\AllocateCustomerPayment;
use App\Actions\Receivables\AmendPaymentAllocation;
use App\Actions\Receivables\IssuePaymentReceipt;
use App\Actions\Receivables\RecordCustomerPayment;
use App\Actions\Receivables\ReverseCustomerPayment;
use App\Actions\Receivables\VerifyCustomerPayment;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Required tests from docs/rebuild/specs/04-billing-and-receivables/Specs.md:
 * "Multiple invoice allocations from one payment", "Partial payment,
 * overpayment, reversal, and amendment", "One receipt per actual payment",
 * "Concurrent numbering and annual reset", "cleared-cheque verification,
 * and post-receipt allocation amendment."
 */
class PaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD',
        ]);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role]);

        return $user;
    }

    private function makeInvoice(float $total): Invoice
    {
        // Invoice's #[Fillable] list deliberately excludes total/balance/
        // amount_paid (they're derived, never directly set from a form) —
        // forceFill them for this fixture, same pattern JobVariationTest
        // uses for Quotation::total.
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => InvoiceType::Invoice,
            'status' => InvoiceStatus::Issued,
            'number' => 'ACM-INV-'.random_int(100000, 999999),
        ]);

        $invoice->forceFill(['total' => $total, 'balance' => $total, 'amount_paid' => 0])->save();

        return $invoice->fresh();
    }

    private function recordPayment(float $amount, string $method = 'bank_transfer'): Payment
    {
        return app(RecordCustomerPayment::class)->record([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'method' => PaymentMethod::from($method),
            'amount' => $amount,
            'currency_code' => 'USD',
            'payment_date' => now(),
            'proof_path' => 'proofs/receipt.pdf',
        ]);
    }

    public function test_a_single_payment_can_be_allocated_across_multiple_invoices(): void
    {
        $invoiceA = $this->makeInvoice(600);
        $invoiceB = $this->makeInvoice(400);
        $payment = $this->recordPayment(1000);
        $owner = $this->userWithRole('owner');

        app(VerifyCustomerPayment::class)->verify($payment, $owner);
        app(AllocateCustomerPayment::class)->allocate($payment->fresh(), [
            $invoiceA->id => 600,
            $invoiceB->id => 400,
        ]);

        $this->assertSame('600.00', $invoiceA->fresh()->amount_paid);
        $this->assertSame('0.00', $invoiceA->fresh()->balance);
        $this->assertSame(InvoiceStatus::Paid, $invoiceA->fresh()->status);

        $this->assertSame('400.00', $invoiceB->fresh()->amount_paid);
        $this->assertSame('0.00', $invoiceB->fresh()->balance);
        $this->assertSame(InvoiceStatus::Paid, $invoiceB->fresh()->status);
    }

    public function test_partial_allocation_leaves_invoice_partially_paid(): void
    {
        $invoice = $this->makeInvoice(1000);
        $payment = $this->recordPayment(400);
        $owner = $this->userWithRole('owner');

        app(VerifyCustomerPayment::class)->verify($payment, $owner);
        app(AllocateCustomerPayment::class)->allocate($payment->fresh(), [$invoice->id => 400]);

        $fresh = $invoice->fresh();
        $this->assertSame('400.00', $fresh->amount_paid);
        $this->assertSame('600.00', $fresh->balance);
        $this->assertSame(InvoiceStatus::Partial, $fresh->status);
        $this->assertGreaterThan(0.0, (float) $fresh->balance);
    }

    public function test_allocating_less_than_the_payment_amount_leaves_an_unallocated_overpayment(): void
    {
        $invoice = $this->makeInvoice(300);
        $payment = $this->recordPayment(1000);
        $owner = $this->userWithRole('owner');

        app(VerifyCustomerPayment::class)->verify($payment, $owner);
        $result = app(AllocateCustomerPayment::class)->allocate($payment->fresh(), [$invoice->id => 300]);

        $this->assertSame('1000.00', $result->amount);
        $this->assertSame('300.00', $invoice->fresh()->amount_paid);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_allocating_more_than_the_payment_amount_throws(): void
    {
        $invoiceA = $this->makeInvoice(600);
        $invoiceB = $this->makeInvoice(600);
        $payment = $this->recordPayment(1000);
        $owner = $this->userWithRole('owner');
        app(VerifyCustomerPayment::class)->verify($payment, $owner);

        $this->expectException(RuntimeException::class);

        app(AllocateCustomerPayment::class)->allocate($payment->fresh(), [
            $invoiceA->id => 600,
            $invoiceB->id => 600,
        ]);
    }

    public function test_allocating_to_an_invoice_of_a_different_client_throws(): void
    {
        $otherClient = Client::create(['company_id' => $this->company->id, 'name' => 'Other Client']);
        $otherInvoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $otherClient->id,
            'type' => InvoiceType::Invoice,
            'status' => InvoiceStatus::Issued,
            'number' => 'ACM-INV-OTHER',
        ]);
        $otherInvoice->forceFill(['total' => 500, 'balance' => 500, 'amount_paid' => 0])->save();

        $payment = $this->recordPayment(500);
        $owner = $this->userWithRole('owner');
        app(VerifyCustomerPayment::class)->verify($payment, $owner);

        $this->expectException(RuntimeException::class);

        app(AllocateCustomerPayment::class)->allocate($payment->fresh(), [$otherInvoice->id => 500]);
    }

    public function test_reversing_a_receipted_payment_drops_the_invoice_paid_amount_but_keeps_the_receipt(): void
    {
        $invoice = $this->makeInvoice(1000);
        $payment = $this->recordPayment(1000);
        $owner = $this->userWithRole('owner');

        app(VerifyCustomerPayment::class)->verify($payment, $owner);
        app(AllocateCustomerPayment::class)->allocate($payment->fresh(), [$invoice->id => 1000]);
        $receipt = app(IssuePaymentReceipt::class)->issue($payment->fresh());

        $this->assertSame('1000.00', $invoice->fresh()->amount_paid);

        $reversed = app(ReverseCustomerPayment::class)->reverse($payment->fresh(), 'Bounced cheque', $owner);

        $this->assertSame(PaymentStatus::Reversed, $reversed->status);
        $this->assertSame('0.00', $invoice->fresh()->amount_paid);
        $this->assertSame('1000.00', $invoice->fresh()->balance);
        // Status auto-transition only applies while the invoice is in a
        // "billable" state (Issued/Partial/Overdue) — once Paid it is left
        // alone by RecalculateInvoiceReceivables per Specs.md; only the
        // derived amount_paid/balance always reflect the reversal.

        $this->assertNotNull($payment->fresh()->receipt);
        $this->assertSame($receipt->number, $payment->fresh()->receipt->number);
        $this->assertSame($receipt->issued_at->toDateTimeString(), $payment->fresh()->receipt->issued_at->toDateTimeString());

        $this->assertDatabaseHas('payment_reversals', [
            'payment_id' => $payment->id,
            'reason' => 'Bounced cheque',
            'reversed_by_user_id' => $owner->id,
        ]);
    }

    public function test_a_second_receipt_cannot_be_issued_for_the_same_payment(): void
    {
        $invoice = $this->makeInvoice(500);
        $payment = $this->recordPayment(500);
        $owner = $this->userWithRole('owner');

        app(VerifyCustomerPayment::class)->verify($payment, $owner);
        app(AllocateCustomerPayment::class)->allocate($payment->fresh(), [$invoice->id => 500]);
        app(IssuePaymentReceipt::class)->issue($payment->fresh());

        $this->expectException(RuntimeException::class);

        app(IssuePaymentReceipt::class)->issue($payment->fresh());
    }

    public function test_verifying_a_cheque_payment_without_a_cleared_date_throws(): void
    {
        $payment = $this->recordPayment(500, 'cheque');
        $owner = $this->userWithRole('owner');

        $this->expectException(RuntimeException::class);

        app(VerifyCustomerPayment::class)->verify($payment, $owner);
    }

    public function test_verifying_a_cheque_payment_with_a_cleared_date_succeeds(): void
    {
        $payment = $this->recordPayment(500, 'cheque');
        $owner = $this->userWithRole('owner');

        $verified = app(VerifyCustomerPayment::class)->verify($payment, $owner, now());

        $this->assertSame(PaymentStatus::Verified, $verified->status);
        $this->assertNotNull($verified->cheque_cleared_at);
    }

    public function test_post_receipt_allocation_can_be_amended(): void
    {
        $invoiceA = $this->makeInvoice(600);
        $invoiceB = $this->makeInvoice(400);
        $payment = $this->recordPayment(1000);
        $owner = $this->userWithRole('owner');

        app(VerifyCustomerPayment::class)->verify($payment, $owner);
        app(AllocateCustomerPayment::class)->allocate($payment->fresh(), [$invoiceA->id => 1000]);
        $receipt = app(IssuePaymentReceipt::class)->issue($payment->fresh());
        $originalNumber = $receipt->number;
        $originalIssuedAt = $receipt->issued_at->toDateTimeString();

        $amendment = app(AmendPaymentAllocation::class)->amend(
            $payment->fresh(),
            [$invoiceA->id => 600, $invoiceB->id => 400],
            'Split payment across two jobs',
            $owner,
        );

        $this->assertSame($receipt->id, $amendment->receipt_id);
        $this->assertSame('Split payment across two jobs', $amendment->reason);
        $this->assertCount(1, $amendment->allocations_before);
        $this->assertSame($invoiceA->id, $amendment->allocations_before[0]['invoice_id']);
        $this->assertCount(2, $amendment->allocations_after);

        $receipt->refresh();
        $this->assertSame($originalNumber, $receipt->number);
        $this->assertSame($originalIssuedAt, $receipt->issued_at->toDateTimeString());

        $this->assertSame('600.00', $invoiceA->fresh()->amount_paid);
        $this->assertSame('400.00', $invoiceB->fresh()->amount_paid);
    }

    public function test_unauthorized_verification_is_denied_for_staff_and_sales(): void
    {
        $payment = $this->recordPayment(500);

        foreach (['staff', 'sales'] as $role) {
            $user = $this->userWithRole($role);

            try {
                app(VerifyCustomerPayment::class)->verify($payment->fresh(), $user);
                $this->fail("Expected RuntimeException for role [{$role}]");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Owner', $e->getMessage());
            }
        }
    }

    public function test_owner_admin_and_accountant_can_verify(): void
    {
        foreach (['owner', 'admin', 'accountant'] as $role) {
            $payment = $this->recordPayment(500);
            $user = $this->userWithRole($role);

            $verified = app(VerifyCustomerPayment::class)->verify($payment, $user);
            $this->assertSame(PaymentStatus::Verified, $verified->status);
        }
    }

    public function test_receipts_are_issued_with_sequential_numbers(): void
    {
        $invoiceA = $this->makeInvoice(100);
        $invoiceB = $this->makeInvoice(100);
        $owner = $this->userWithRole('owner');

        $paymentA = $this->recordPayment(100);
        app(VerifyCustomerPayment::class)->verify($paymentA, $owner);
        app(AllocateCustomerPayment::class)->allocate($paymentA->fresh(), [$invoiceA->id => 100]);
        $receiptA = app(IssuePaymentReceipt::class)->issue($paymentA->fresh());

        $paymentB = $this->recordPayment(100);
        app(VerifyCustomerPayment::class)->verify($paymentB, $owner);
        app(AllocateCustomerPayment::class)->allocate($paymentB->fresh(), [$invoiceB->id => 100]);
        $receiptB = app(IssuePaymentReceipt::class)->issue($paymentB->fresh());

        $this->assertStringEndsWith('0001', $receiptA->number);
        $this->assertStringEndsWith('0002', $receiptB->number);
        $this->assertStringContainsString('-RCT-', $receiptA->number);
        $this->assertStringContainsString('-RCT-', $receiptB->number);
    }
}
