<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Mail\CompanyTemplatedMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * App\Services\BillingMailer closes the gap flagged in
 * docs/filament-admin-layout-design.md §3.3 — the invoice/quote/payment
 * templates and reminder schedule stored on CompanySetting are now
 * actually dispatched, not just stored config.
 */
class BillingMailerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);
        $this->contact = Contact::create([
            'client_id' => $this->client->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'is_primary' => true,
        ]);
    }

    public function test_sending_an_invoice_creates_an_invitation_and_marks_it_sent(): void
    {
        Mail::fake();

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'number' => 'INV-0001',
        ]);
        $invoice->forceFill(['total' => 150, 'balance' => 150])->save();

        $invitation = app(BillingMailer::class)->sendInvoice($invoice);

        $this->assertNotNull($invitation->sent_at);
        $this->assertSame($this->contact->id, $invitation->contact_id);
        $this->assertSame(InvoiceStatus::Sent, $invoice->fresh()->status);

        Mail::assertSent(CompanyTemplatedMail::class, function (CompanyTemplatedMail $mail) use ($invitation) {
            return $mail->hasTo('jane@example.com')
                && str_contains($mail->subjectLine, 'INV-0001')
                && str_contains($mail->bodyText, url('/portal/'.$invitation->key));
        });
    }

    public function test_sending_does_not_downgrade_an_already_sent_status(): void
    {
        Mail::fake();

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'viewed',
            'number' => 'INV-0002',
        ]);

        app(BillingMailer::class)->sendInvoice($invoice);

        $this->assertSame(InvoiceStatus::Viewed, $invoice->fresh()->status);
    }

    public function test_sending_uses_the_companys_stored_template_and_renders_placeholders(): void
    {
        Mail::fake();

        CompanySetting::create([
            'company_id' => $this->company->id,
            'invoice_email_subject' => 'Your bill {{invoice_number}}',
            'invoice_email_body' => 'Hi {{contact_name}}, please pay {{amount}}. Link: {{portal_link}}',
        ]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'number' => 'INV-0003',
        ]);
        $invoice->forceFill(['total' => 99.5])->save();

        $invitation = app(BillingMailer::class)->sendInvoice($invoice);

        Mail::assertSent(CompanyTemplatedMail::class, function (CompanyTemplatedMail $mail) use ($invitation) {
            return $mail->subjectLine === 'Your bill INV-0003'
                && $mail->bodyText === 'Hi Jane Doe, please pay 99.50. Link: '.url('/portal/'.$invitation->key);
        });
    }

    public function test_sending_a_quote_uses_the_quote_template(): void
    {
        Mail::fake();

        $quote = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'quote',
            'status' => 'draft',
            'number' => 'QUO-0001',
        ]);

        app(BillingMailer::class)->sendQuote($quote);

        Mail::assertSent(CompanyTemplatedMail::class, fn (CompanyTemplatedMail $mail) => str_contains($mail->subjectLine, 'Quote QUO-0001'));
    }

    public function test_sending_a_reminder_prefixes_the_subject(): void
    {
        Mail::fake();

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'number' => 'INV-0004',
        ]);

        app(BillingMailer::class)->sendReminder($invoice, 1);

        Mail::assertSent(CompanyTemplatedMail::class, fn (CompanyTemplatedMail $mail) => str_starts_with($mail->subjectLine, 'Reminder: '));
    }

    public function test_sending_throws_when_the_client_has_no_contact_with_an_email(): void
    {
        $bareClient = Client::create(['company_id' => $this->company->id, 'name' => 'No Contact Co']);
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $bareClient->id,
            'type' => 'invoice',
            'status' => 'draft',
        ]);

        $this->expectException(RuntimeException::class);

        app(BillingMailer::class)->sendInvoice($invoice);
    }

    public function test_sending_a_payment_receipt_uses_the_payment_template(): void
    {
        Mail::fake();

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'number' => 'INV-0005',
        ]);
        $payment = Payment::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'status' => 'completed',
        ]);

        app(BillingMailer::class)->sendPaymentReceipt($payment);

        Mail::assertSent(CompanyTemplatedMail::class, fn (CompanyTemplatedMail $mail) => $mail->hasTo('jane@example.com') && str_contains($mail->bodyText, '50.00'));
    }
}
