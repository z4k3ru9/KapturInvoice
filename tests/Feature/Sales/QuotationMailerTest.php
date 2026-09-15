<?php

namespace Tests\Feature\Sales;

use App\Mail\CompanyTemplatedMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\Sales\QuotationMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * App\Services\Sales\QuotationMailer — the Phase 03 Quotation's own email
 * send, distinct from App\Services\BillingMailer::sendQuote() (the legacy
 * Invoice/type=quote model). There is no portal route for a Quotation
 * today, so this only ever attaches the PDF — no "view online" link.
 */
class QuotationMailerTest extends TestCase
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

    private function makeQuotation(): Quotation
    {
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'KA-QUO-2026090001',
            'status' => 'draft',
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'title' => 'Widget',
            'quantity' => 2,
            'unit_cost' => 500,
            'line_total' => 1000,
        ]);

        $quotation->forceFill(['subtotal' => 1000, 'total' => 1000])->save();

        return $quotation;
    }

    public function test_sending_a_quotation_emails_the_billing_contact_with_the_pdf_attached(): void
    {
        Mail::fake();

        $quotation = $this->makeQuotation();

        app(QuotationMailer::class)->sendQuotation($quotation);

        Mail::assertSent(CompanyTemplatedMail::class, function (CompanyTemplatedMail $mail) use ($quotation) {
            if (! $mail->hasTo('jane@example.com') || ! str_contains($mail->subjectLine, $quotation->number)) {
                return false;
            }

            if (count($mail->pdfAttachments) !== 1) {
                return false;
            }

            $attachment = $mail->pdfAttachments[0];

            if (! $attachment instanceof Attachment || $attachment->as !== "{$quotation->number}.pdf" || $attachment->mime !== 'application/pdf') {
                return false;
            }

            $data = $attachment->attachWith(fn ($path) => null, fn ($resolveData) => $resolveData());

            return is_string($data) && str_starts_with($data, '%PDF');
        });
    }

    public function test_sending_a_quotation_does_not_include_a_portal_link(): void
    {
        Mail::fake();

        $quotation = $this->makeQuotation();

        app(QuotationMailer::class)->sendQuotation($quotation);

        Mail::assertSent(CompanyTemplatedMail::class, fn (CompanyTemplatedMail $mail) => ! str_contains($mail->bodyText, 'http'));
    }

    public function test_sending_uses_the_companys_stored_quotation_template(): void
    {
        Mail::fake();

        CompanySetting::create([
            'company_id' => $this->company->id,
            'quotation_email_subject' => 'Your quotation {{quotation_number}}',
            'quotation_email_body' => 'Hi {{contact_name}}, total is {{amount}}.',
        ]);

        $quotation = $this->makeQuotation();

        app(QuotationMailer::class)->sendQuotation($quotation);

        Mail::assertSent(CompanyTemplatedMail::class, fn (CompanyTemplatedMail $mail) => $mail->subjectLine === 'Your quotation KA-QUO-2026090001'
            && $mail->bodyText === 'Hi Jane Doe, total is 1,000.00.');
    }

    public function test_sending_throws_when_the_client_has_no_contact_with_an_email(): void
    {
        $bareClient = Client::create(['company_id' => $this->company->id, 'name' => 'No Contact Co']);
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $bareClient->id,
            'number' => 'KA-QUO-2026090002',
            'status' => 'draft',
        ]);

        $this->expectException(RuntimeException::class);

        app(QuotationMailer::class)->sendQuotation($quotation);
    }

    public function test_sending_ccs_the_given_recipients(): void
    {
        Mail::fake();

        $quotation = $this->makeQuotation();

        app(QuotationMailer::class)->sendQuotation($quotation, cc: ['extra@example.com']);

        Mail::assertSent(CompanyTemplatedMail::class, fn (CompanyTemplatedMail $mail) => $mail->hasCc('extra@example.com'));
    }
}
