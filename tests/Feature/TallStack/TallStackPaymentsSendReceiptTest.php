<?php

namespace Tests\Feature\TallStack;

use App\Actions\Receivables\IssuePaymentReceipt;
use App\Livewire\TallStackPayments;
use App\Mail\CompanyTemplatedMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers reconnecting App\Services\BillingMailer::sendPaymentReceipt() to
 * the UI — it existed but had no caller anywhere in the TallStackUI
 * rebuild (Filament's Payments table used to have a Send-receipt action;
 * it was never reconnected). This tests the Livewire wiring
 * (TallStackPayments::sendReceipt()) only; the mailer's own template
 * rendering/contact-resolution behavior is already covered by
 * tests/Feature/BillingMailerTest.php.
 */
class TallStackPaymentsSendReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);
        Contact::create([
            'client_id' => $this->client->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'is_primary' => true,
        ]);

        $this->actingAs($this->user);
    }

    private function verifiedPaymentWithReceipt(): Payment
    {
        $payment = Payment::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'amount' => 100,
            'status' => 'verified',
        ]);

        app(IssuePaymentReceipt::class)->issue($payment->fresh());

        return $payment->fresh();
    }

    public function test_send_receipt_emails_the_clients_contact(): void
    {
        Mail::fake();

        $payment = $this->verifiedPaymentWithReceipt();

        Livewire::test(TallStackPayments::class, ['company' => $this->company])
            ->call('sendReceipt', $payment->id)
            ->assertHasNoErrors();

        Mail::assertSent(CompanyTemplatedMail::class, fn (CompanyTemplatedMail $mail) => $mail->hasTo('jane@example.com'));
    }

    public function test_send_receipt_does_nothing_for_a_payment_without_a_receipt(): void
    {
        Mail::fake();

        $payment = Payment::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'amount' => 100,
            'status' => 'pending',
        ]);

        Livewire::test(TallStackPayments::class, ['company' => $this->company])
            ->call('sendReceipt', $payment->id)
            ->assertHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_send_receipt_does_nothing_for_another_companys_payment(): void
    {
        Mail::fake();

        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        $otherClient = Client::create(['company_id' => $otherCompany->id, 'name' => 'Other Client']);
        Contact::create([
            'client_id' => $otherClient->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john@example.com',
            'is_primary' => true,
        ]);
        $otherPayment = Payment::create([
            'company_id' => $otherCompany->id,
            'client_id' => $otherClient->id,
            'amount' => 100,
            'status' => 'verified',
        ]);
        app(IssuePaymentReceipt::class)->issue($otherPayment->fresh());

        Livewire::test(TallStackPayments::class, ['company' => $this->company])
            ->call('sendReceipt', $otherPayment->fresh()->id)
            ->assertHasNoErrors();

        Mail::assertNothingSent();
    }
}
