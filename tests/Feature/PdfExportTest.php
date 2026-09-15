<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Credit;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PDF export — see docs/filament-admin-layout-design.md §7. Both the
 * admin-side controllers (InvoicePdfController/CreditPdfController) and
 * the public portal one share the same "outside the Filament panel, so
 * check the tenant explicitly" pattern as DocumentDownloadController.
 */
class PdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_pdf_downloads_for_a_user_who_belongs_to_the_owning_company(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $company->users()->attach($user, ['role' => 'owner']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $invoice = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0001']);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Consulting', 'quantity' => 1, 'unit_cost' => 100, 'line_total' => 100]);

        $this->actingAs($user)
            ->get(route('invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_invoice_pdf_is_forbidden_for_a_user_outside_the_owning_company(): void
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $company->users()->attach($owner, ['role' => 'owner']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $invoice = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0001']);

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('invoices.pdf', $invoice))
            ->assertForbidden();
    }

    public function test_credit_pdf_downloads_for_a_user_who_belongs_to_the_owning_company(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $company->users()->attach($user, ['role' => 'owner']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $credit = Credit::create(['company_id' => $company->id, 'client_id' => $client->id, 'number' => 'CRE-0001', 'amount' => 50]);

        $this->actingAs($user)
            ->get(route('credits.pdf', $credit))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_portal_invoice_pdf_downloads_without_auth_for_the_domain_matched_company(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com']);
        $invoice = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0001']);
        $invitation = Invitation::create(['invoice_id' => $invoice->id, 'contact_id' => $contact->id]);

        $this->get("http://acme.test/portal/{$invitation->key}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_portal_invoice_pdf_404s_for_a_different_domain(): void
    {
        $owner = Company::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
        $other = Company::create(['name' => 'Other', 'slug' => 'other', 'domain' => 'other.test']);
        $client = Client::create(['company_id' => $owner->id, 'name' => 'Client Co']);
        $contact = Contact::create(['client_id' => $client->id, 'first_name' => 'Jane']);
        $invoice = Invoice::create(['company_id' => $owner->id, 'client_id' => $client->id, 'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0001']);
        $invitation = Invitation::create(['invoice_id' => $invoice->id, 'contact_id' => $contact->id]);

        $this->get("http://{$other->domain}/portal/{$invitation->key}/pdf")->assertNotFound();
    }

    public function test_invoice_pdf_template_includes_company_and_client_tax_ids(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD', 'tax_number' => 'CO-TAX-999']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co', 'tax_number' => 'CLIENT-TAX-123']);
        $invoice = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0001']);
        $invoice->loadMissing('client', 'company', 'items');

        $html = view('pdf.invoice', ['invoice' => $invoice])->render();

        $this->assertStringContainsString('CO-TAX-999', $html);
        $this->assertStringContainsString('CLIENT-TAX-123', $html);
    }

    public function test_credit_pdf_template_includes_company_and_client_tax_ids(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD', 'tax_number' => 'CO-TAX-999']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co', 'tax_number' => 'CLIENT-TAX-123']);
        $credit = Credit::create(['company_id' => $company->id, 'client_id' => $client->id, 'number' => 'CRE-0001', 'amount' => 50]);
        $credit->loadMissing('client', 'company');

        $html = view('pdf.credit', ['credit' => $credit])->render();

        $this->assertStringContainsString('CO-TAX-999', $html);
        $this->assertStringContainsString('CLIENT-TAX-123', $html);
    }

    public function test_invoice_pdf_template_embeds_the_company_logo_as_a_data_uri(): void
    {
        Storage::fake(config('filesystems.default'));
        Storage::disk(config('filesystems.default'))->put('logos/acme.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD', 'logo_path' => 'logos/acme.png']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $invoice = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0001']);
        $invoice->loadMissing('client', 'company', 'items');

        $html = view('pdf.invoice', ['invoice' => $invoice])->render();

        $this->assertStringContainsString('data:image/png;base64,', $html);
    }

    /**
     * docs/rebuild/specs/06-documents-portal-reporting/Specs.md: "Printed
     * documents default to Bahasa Indonesia with per-document English
     * override." No CompanySetting row at all should still default to 'id'
     * — see Invoice::resolveDocumentLanguage().
     */
    public function test_invoice_pdf_renders_bahasa_indonesia_labels_by_default_with_no_company_setting_row(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'number' => 'INV-0001',
            'due_date' => '2026-10-01',
        ]);
        $invoice->loadMissing('client', 'company', 'items');

        $html = view('pdf.invoice', ['invoice' => $invoice])->render();

        $this->assertStringContainsString('Jatuh Tempo', $html);
        $this->assertStringNotContainsString('>Due:', $html);
    }

    public function test_invoice_pdf_renders_bahasa_indonesia_labels_when_company_default_is_id(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        CompanySetting::create(['company_id' => $company->id, 'default_document_language' => 'id']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'number' => 'INV-0001',
            'due_date' => '2026-10-01',
        ]);
        $invoice->loadMissing('client', 'company', 'items');

        $html = view('pdf.invoice', ['invoice' => $invoice])->render();

        $this->assertStringContainsString('Jatuh Tempo', $html);
        $this->assertStringContainsString('FAKTUR', $html);
    }

    public function test_invoice_pdf_renders_english_labels_when_document_language_override_is_en(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        CompanySetting::create(['company_id' => $company->id, 'default_document_language' => 'id']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'number' => 'INV-0001',
            'due_date' => '2026-10-01',
            'document_language' => 'en',
        ]);
        $invoice->loadMissing('client', 'company', 'items');

        $html = view('pdf.invoice', ['invoice' => $invoice])->render();

        $this->assertStringContainsString('Due:', $html);
        $this->assertStringContainsString('INVOICE', $html);
        $this->assertStringNotContainsString('Jatuh Tempo', $html);
    }

    public function test_credit_pdf_renders_bahasa_indonesia_labels_by_default(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $credit = Credit::create(['company_id' => $company->id, 'client_id' => $client->id, 'number' => 'CRE-0001', 'amount' => 50]);
        $credit->loadMissing('client', 'company');

        $html = view('pdf.credit', ['credit' => $credit])->render();

        $this->assertStringContainsString('NOTA KREDIT', $html);
        $this->assertStringContainsString(__('documents.amount', [], 'id'), $html);
    }

    public function test_credit_pdf_renders_english_labels_when_document_language_override_is_en(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $credit = Credit::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'CRE-0001',
            'amount' => 50,
            'document_language' => 'en',
        ]);
        $credit->loadMissing('client', 'company');

        $html = view('pdf.credit', ['credit' => $credit])->render();

        $this->assertStringContainsString('CREDIT', $html);
        $this->assertStringNotContainsString('NOTA KREDIT', $html);
    }
}
