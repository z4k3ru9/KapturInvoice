<?php

namespace Tests\Feature\Receivables;

use App\Actions\Receivables\AllocateCustomerPayment;
use App\Actions\Receivables\RecordCustomerPayment;
use App\Actions\Receivables\VerifyCustomerPayment;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Services\Receivables\RecalculateInvoiceReceivables — added alongside
 * App\Console\Commands\MarkInvoicesOverdue: confirms a payment event on an
 * already-Overdue invoice re-derives Overdue (when still past due and not
 * fully paid) instead of silently clobbering it back to Issued/Partial,
 * and that a fully-paid Overdue invoice still correctly resolves to Paid.
 */
class RecalculateInvoiceReceivablesOverdueTest extends TestCase
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

    private function makeOverdueInvoice(float $total): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => InvoiceType::Invoice,
            'status' => InvoiceStatus::Overdue,
            'number' => 'ACM-INV-'.random_int(100000, 999999),
            'due_date' => now()->subWeek()->toDateString(),
        ]);

        $invoice->forceFill(['total' => $total, 'balance' => $total, 'amount_paid' => 0])->save();

        return $invoice->fresh();
    }

    private function recordPayment(float $amount): Payment
    {
        return app(RecordCustomerPayment::class)->record([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'method' => PaymentMethod::BankTransfer,
            'amount' => $amount,
            'currency_code' => 'USD',
            'payment_date' => now(),
            'proof_path' => 'proofs/receipt.pdf',
        ]);
    }

    public function test_a_partial_payment_on_an_overdue_invoice_stays_overdue_not_issued_or_partial(): void
    {
        $invoice = $this->makeOverdueInvoice(1000);
        $payment = $this->recordPayment(400);
        $owner = $this->userWithRole('owner');

        app(VerifyCustomerPayment::class)->verify($payment, $owner);
        app(AllocateCustomerPayment::class)->allocate($payment->fresh(), [$invoice->id => 400]);

        $this->assertSame('400.00', $invoice->fresh()->amount_paid);
        $this->assertSame('600.00', $invoice->fresh()->balance);
        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
    }

    public function test_fully_paying_an_overdue_invoice_resolves_to_paid(): void
    {
        $invoice = $this->makeOverdueInvoice(1000);
        $payment = $this->recordPayment(1000);
        $owner = $this->userWithRole('owner');

        app(VerifyCustomerPayment::class)->verify($payment, $owner);
        app(AllocateCustomerPayment::class)->allocate($payment->fresh(), [$invoice->id => 1000]);

        $this->assertSame('0.00', $invoice->fresh()->balance);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }
}
