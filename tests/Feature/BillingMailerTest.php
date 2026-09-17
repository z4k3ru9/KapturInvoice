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
use Illuminate\Mail\Mailables\Attachment;
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

    public function test_sending_prefers_the_billing_contact_over_the_primary_contact(): void
    {
        Mail::fake();

        $billingContact = Contact::create([
            'client_id' => $this->client->id,
            'first_name' => 'Bill',
            'last_name' => 'Ing',
            'email' => 'billing@example.com',
            'is_primary' => false,
            'is_billing_contact' => true,
        ]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'number' => 'INV-0006',
        ]);

        app(BillingMailer::class)->sendInvoice($invoice);

        Mail::assertSent(CompanyTemplatedMail::class, fn (CompanyTemplatedMail $mail) => $mail->hasTo($billingContact->email) && ! $mail->hasTo('jane@example.com'));
    }

    public function test_sending_an_invoice_ccs_the_given_recipients(): void
    {
        Mail::fake();

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'number' => 'INV-0007',
        ]);

        app(BillingMailer::class)->sendInvoice($invoice, cc: ['extra@example.com']);

        Mail::assertSent(CompanyTemplatedMail::class, fn (CompanyTemplatedMail $mail) => $mail->hasCc('extra@example.com'));
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

    public function test_sending_an_invoice_attaches_the_invoice_pdf(): void
    {
        Mail::fake();

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'number' => 'INV-0008',
        ]);
        $invoice->forceFill(['total' => 150, 'balance' => 150])->save();

        app(BillingMailer::class)->sendInvoice($invoice);

        Mail::assertSent(CompanyTemplatedMail::class, function (CompanyTemplatedMail $mail) {
            return $this->assertPdfAttachment($mail, 'INV-0008.pdf');
        });
    }

    public function test_sending_a_quote_attaches_the_invoice_pdf(): void
    {
        Mail::fake();

        $quote = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'quote',
            'status' => 'draft',
            'number' => 'QUO-0002',
        ]);

        app(BillingMailer::class)->sendQuote($quote);

        Mail::assertSent(CompanyTemplatedMail::class, function (CompanyTemplatedMail $mail) {
            return $this->assertPdfAttachment($mail, 'QUO-0002.pdf');
        });
    }

    public function test_sending_a_payment_receipt_does_not_attach_a_pdf(): void
    {
        Mail::fake();

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'number' => 'INV-0009',
        ]);
        $payment = Payment::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'status' => 'completed',
        ]);

        app(BillingMailer::class)->sendPaymentReceipt($payment);

        Mail::assertSent(CompanyTemplatedMail::class, fn (CompanyTemplatedMail $mail) => $mail->pdfAttachments === []);
    }

    /**
     * `CompanySetting::mail_config` (Email & Reminders settings page) is
     * opt-in per company — App\Services\CompanyMailerResolver falls back
     * to the app's own default mailer/from address when it's unset, which
     * is what every other test in this file exercises implicitly. This
     * confirms the override path itself actually reaches the outgoing
     * Mailable when a company has configured its own.
     */
    public function test_a_companys_own_mail_config_overrides_the_from_address(): void
    {
        Mail::fake();

        CompanySetting::query()->create([
            'company_id' => $this->company->id,
            'mail_config' => [
                'host' => 'smtp.acme-mail.test',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'acme',
                'password' => 'secret-api-key',
                'from_address' => 'billing@acme.test',
                'from_name' => 'Acme Billing',
            ],
        ]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'number' => 'INV-0010',
        ]);
        $payment = Payment::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'status' => 'completed',
        ]);

        app(BillingMailer::class)->sendPaymentReceipt($payment);

        Mail::assertSent(CompanyTemplatedMail::class, fn (CompanyTemplatedMail $mail) => $mail->fromAddress === 'billing@acme.test' && $mail->fromName === 'Acme Billing');
    }

    /**
     * The body template moved from a plain-text field to TallStackUI's
     * <x-editor> (App\Livewire\TallStackSettingsEmail) — resources/views/
     * emails/plain.blade.php now renders it raw once it looks like real
     * HTML (server-sanitized via App\Support\Html\RichTextSanitizer on
     * save, on top of the editor's own client-side sanitizer), instead of
     * escaping and nl2br()-ing it as a plain-text template.
     */
    public function test_sending_renders_a_formatted_html_body_unescaped(): void
    {
        Mail::fake();

        CompanySetting::create([
            'company_id' => $this->company->id,
            'invoice_email_subject' => 'Your bill {{invoice_number}}',
            'invoice_email_body' => '<p>Hi {{contact_name}}, please pay <strong>{{amount}}</strong>.</p><ul><li>Thank you</li></ul>',
        ]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'number' => 'INV-0010',
        ]);
        $invoice->forceFill(['total' => 99.5])->save();

        app(BillingMailer::class)->sendInvoice($invoice);

        Mail::assertSent(CompanyTemplatedMail::class, function (CompanyTemplatedMail $mail) {
            $rendered = view('emails.plain', ['body' => $mail->bodyText])->render();

            return str_contains($rendered, '<strong>99.50</strong>')
                && str_contains($rendered, '<li>Thank you</li>')
                && ! str_contains($rendered, '&lt;strong&gt;');
        });
    }

    /**
     * App\Services\EmailTemplateRenderer::renderHtml() closes a real HTML-
     * injection gap: the body template itself is server-sanitized
     * (App\Support\Html\RichTextSanitizer), but its `{{client_name}}`/
     * `{{contact_name}}` token *values* are ordinary free-text data — a
     * client could be named something that looks like a tag. Since
     * resources/views/emails/plain.blade.php renders an HTML-looking body
     * unescaped, an unescaped token substitution would let that markup
     * reach the outbound email raw.
     */
    public function test_sending_escapes_a_malicious_client_name_inside_an_html_body_template(): void
    {
        Mail::fake();

        CompanySetting::create([
            'company_id' => $this->company->id,
            'invoice_email_subject' => 'Your bill {{invoice_number}}',
            'invoice_email_body' => '<p>Hi {{contact_name}} from {{client_name}}, please pay {{amount}}.</p>',
        ]);

        $this->client->update(['name' => '<img src=x onerror=alert(1)>']);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'number' => 'INV-0012',
        ]);
        $invoice->forceFill(['total' => 10])->save();

        app(BillingMailer::class)->sendInvoice($invoice);

        Mail::assertSent(CompanyTemplatedMail::class, function (CompanyTemplatedMail $mail) {
            $rendered = view('emails.plain', ['body' => $mail->bodyText])->render();

            return ! str_contains($rendered, '<img src=x onerror=alert(1)>')
                && str_contains($rendered, '&lt;img src=x onerror=alert(1)&gt;')
                // The template's own markup is untouched — only the token
                // value was escaped, not the surrounding sanitized HTML.
                && str_contains($rendered, '<p>Hi Jane Doe from');
        });
    }

    /**
     * A company that hasn't customized a template yet still falls back to
     * BillingMailer::templateFor()'s hardcoded plain-text default, which
     * carries literal "\n" line breaks and no HTML — the view has to
     * still treat that shape correctly (escaped, nl2br()'d), not render
     * it raw.
     */
    public function test_sending_renders_the_plain_text_default_template_escaped_with_line_breaks(): void
    {
        Mail::fake();

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'number' => 'INV-0011',
        ]);
        $invoice->forceFill(['total' => 10])->save();

        app(BillingMailer::class)->sendInvoice($invoice);

        Mail::assertSent(CompanyTemplatedMail::class, function (CompanyTemplatedMail $mail) {
            $rendered = view('emails.plain', ['body' => $mail->bodyText])->render();

            return str_contains($rendered, '<br')
                && ! str_contains($rendered, '<p>');
        });
    }

    /**
     * `Mailable::hasAttachment()` compares raw resolved attachment
     * bytes, which we can't recreate independently of the mailer's own
     * PDF render here — so this asserts the attachment that was
     * actually queued has the expected filename/mime and looks like a
     * real PDF, via the public `Attachment::$as`/`$mime` properties and
     * the resolver closure `App\Mail\CompanyTemplatedMail::attachments()`
     * exposes through `$pdfAttachments`.
     */
    private function assertPdfAttachment(CompanyTemplatedMail $mail, string $expectedName): bool
    {
        if (count($mail->pdfAttachments) !== 1) {
            return false;
        }

        $attachment = $mail->pdfAttachments[0];

        if (! $attachment instanceof Attachment || $attachment->as !== $expectedName || $attachment->mime !== 'application/pdf') {
            return false;
        }

        $data = $attachment->attachWith(fn ($path) => null, fn ($resolveData) => $resolveData());

        return is_string($data) && str_starts_with($data, '%PDF');
    }
}
