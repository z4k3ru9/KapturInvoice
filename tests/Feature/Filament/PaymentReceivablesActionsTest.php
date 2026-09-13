<?php

namespace Tests\Feature\Filament;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Exercises the Phase 04 (docs/rebuild/specs/04-billing-and-receivables)
 * Verify/Allocate/Issue receipt/Reverse table actions wired onto
 * App\Filament\Resources\Payments\Tables\PaymentsTable — the underlying
 * action classes (App\Actions\Receivables\*) already have thorough
 * coverage in tests/Feature/Receivables/PaymentWorkflowTest.php; this file
 * proves the Livewire wiring itself actually calls them.
 */
class PaymentReceivablesActionsTest extends TestCase
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
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    private function issuedInvoice(float $total = 1000): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => InvoiceStatus::Issued,
            'number' => 'ACME-INV-'.uniqid(),
        ]);
        $invoice->forceFill(['total' => $total, 'balance' => $total, 'amount_paid' => 0])->save();

        return $invoice;
    }

    private function pendingPayment(float $amount = 1000, string $method = 'bank_transfer'): Payment
    {
        return Payment::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'amount' => $amount,
            'method' => $method,
            'status' => PaymentStatus::Pending,
            'proof_path' => 'payment-proofs/proof.pdf',
        ]);
    }

    public function test_the_verify_table_action_verifies_a_bank_transfer_payment(): void
    {
        $payment = $this->pendingPayment();

        Livewire::test(ListPayments::class)
            ->callTableAction('verify', $payment);

        $this->assertSame(PaymentStatus::Verified, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->verified_at);
    }

    public function test_the_verify_table_action_requires_a_cleared_date_for_a_cheque(): void
    {
        $payment = $this->pendingPayment(method: PaymentMethod::Cheque->value);

        Livewire::test(ListPayments::class)
            ->callTableAction('verify', $payment);

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);

        Livewire::test(ListPayments::class)
            ->callTableAction('verify', $payment, data: ['cheque_cleared_at' => now()->toDateString()]);

        $this->assertSame(PaymentStatus::Verified, $payment->fresh()->status);
    }

    public function test_the_allocate_table_action_splits_a_payment_across_two_invoices(): void
    {
        $payment = $this->pendingPayment(1000);
        $invoiceA = $this->issuedInvoice(600);
        $invoiceB = $this->issuedInvoice(400);

        Livewire::test(ListPayments::class)
            ->callTableAction('allocate', $payment, data: [
                'allocations' => [
                    ['invoice_id' => $invoiceA->id, 'amount' => 600],
                    ['invoice_id' => $invoiceB->id, 'amount' => 400],
                ],
            ]);

        $this->assertSame(2, $payment->fresh()->allocations()->count());
    }

    public function test_the_issue_receipt_table_action_issues_a_receipt_for_a_verified_payment(): void
    {
        $payment = $this->pendingPayment(500);
        $invoice = $this->issuedInvoice(500);

        Livewire::test(ListPayments::class)
            ->callTableAction('allocate', $payment, data: ['allocations' => [['invoice_id' => $invoice->id, 'amount' => 500]]])
            ->callTableAction('verify', $payment);

        Livewire::test(ListPayments::class)
            ->callTableAction('issueReceipt', $payment);

        $this->assertNotNull($payment->fresh()->receipt);
        $this->assertNotNull($payment->fresh()->receipt->number);
    }

    public function test_the_reverse_table_action_reverses_a_verified_payment(): void
    {
        $payment = $this->pendingPayment(500);

        Livewire::test(ListPayments::class)
            ->callTableAction('verify', $payment);

        Livewire::test(ListPayments::class)
            ->callTableAction('reverse', $payment, data: ['reason' => 'Bank confirmed the transfer bounced']);

        $this->assertSame(PaymentStatus::Reversed, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->reversal);
    }
}
