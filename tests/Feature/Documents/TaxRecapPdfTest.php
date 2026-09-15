<?php

namespace Tests\Feature\Documents;

use App\Actions\Billing\IssueInvoice;
use App\Enums\PricingMode;
use App\Enums\TaxCategory;
use App\Http\Controllers\TaxRecapPdfController;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CompanyTaxSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRecap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tax recap joins the required launch document coverage list —
 * docs/rebuild/specs/06b-ux-browser-soa/Specs.md: "Bahasa default, English
 * override, translation keys, A4 layout, rendering test" for every launch
 * document type. Mirrors tests/Feature/PdfExportTest.php's fixture style.
 */
class TaxRecapPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The `tax_recap_*` translation keys are proposed in this task's
        // final report for a merge into resources/lang/{id,en}/documents.php
        // alongside the other Phase 06B document agents' own new keys —
        // this test registers the exact same key/value pairs at runtime
        // (rather than editing those shared, concurrently-edited files
        // directly) so the rendering/locale-switching behavior is verified
        // now, independent of when the merge lands.
        app('translator')->addLines([
            'documents.tax_recap_title' => 'Rekapitulasi Pajak',
            'documents.tax_recap_reporting_period' => 'Periode Pelaporan',
            'documents.tax_recap_external_reference' => 'Referensi Eksternal',
            'documents.tax_recap_manual_entry_status' => 'Status Entri',
            'documents.tax_recap_filing_date' => 'Tanggal Pelaporan',
            'documents.tax_recap_taxable_base' => 'Dasar Pengenaan Pajak',
            'documents.tax_recap_attachment_reference' => 'Referensi Lampiran',
        ], 'id');

        app('translator')->addLines([
            'documents.tax_recap_title' => 'Tax Recap',
            'documents.tax_recap_reporting_period' => 'Reporting Period',
            'documents.tax_recap_external_reference' => 'External Reference',
            'documents.tax_recap_manual_entry_status' => 'Entry Status',
            'documents.tax_recap_filing_date' => 'Filing Date',
            'documents.tax_recap_taxable_base' => 'Taxable Base',
            'documents.tax_recap_attachment_reference' => 'Attachment Reference',
        ], 'en');

        // routes/web.php is intentionally left for a separate merge pass
        // (see this task's final report for the exact line to add) —
        // registered here so the controller's auth/tenant-scoping behavior
        // is exercised now rather than only after that merge lands.
        if (! Route::has('tax-recaps.pdf')) {
            Route::get('/tax-recaps/{taxRecap}/pdf', TaxRecapPdfController::class)
                ->middleware('auth')
                ->name('tax-recaps.pdf');

            // RouteServiceProvider::boot() refreshes the name look-up table
            // once, right after routes/web.php loads — before this dynamic
            // registration runs — so it must be refreshed again here or
            // route('tax-recaps.pdf', ...) never resolves.
            Route::getRoutes()->refreshNameLookups();
        }
    }

    private function axenCompany(): Company
    {
        $company = Company::create([
            'name' => 'Axen Technology Indonesia',
            'slug' => 'axen-technology-indonesia',
            'code' => 'ATI',
            'currency_code' => 'IDR',
        ]);

        CompanyTaxSetting::create([
            'company_id' => $company->id,
            'tax_enabled' => true,
            'standard_tax_rate' => 12.00,
            'dpp_factor_numerator' => 11,
            'dpp_factor_denominator' => 12,
        ]);

        return $company;
    }

    private function owner(Company $company): User
    {
        $user = User::factory()->create();
        $company->users()->attach($user, ['role' => 'owner']);

        return $user;
    }

    private function issuedTaxableInvoice(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'pricing_mode' => PricingMode::Exclusive,
            'currency_code' => 'IDR',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Service',
            'quantity' => 1,
            'unit_cost' => 10000000,
            'tax_category' => TaxCategory::StandardTaxable,
        ]);

        return app(IssueInvoice::class)->issue($invoice, $this->owner($company));
    }

    public function test_issuing_a_taxable_invoice_assigns_a_tax_coded_number_to_its_tax_recap(): void
    {
        $company = $this->axenCompany();

        $issued = $this->issuedTaxableInvoice($company);

        $taxRecap = $issued->taxRecap;

        $this->assertNotNull($taxRecap, 'a taxable issued invoice must generate a TaxRecap');
        $this->assertNotNull($taxRecap->number);
        $this->assertStringContainsString('-TAX-', $taxRecap->number);
        $this->assertStringStartsWith('ATI-TAX-', $taxRecap->number);
    }

    public function test_tax_recap_pdf_renders_bahasa_indonesia_labels_by_default(): void
    {
        $company = $this->axenCompany();
        $issued = $this->issuedTaxableInvoice($company);
        $taxRecap = $issued->taxRecap;
        $taxRecap->loadMissing('invoice.client', 'invoice.company', 'invoice.taxSnapshot');

        $html = view('pdf.tax-recap', ['taxRecap' => $taxRecap])->render();

        $this->assertStringContainsString($taxRecap->number, $html);
        $this->assertStringContainsString(__('documents.tax_recap_title', [], 'id'), $html);
        $this->assertStringContainsString(number_format((float) $issued->taxSnapshot->tax_total, 2), $html);
    }

    public function test_tax_recap_pdf_renders_english_labels_when_document_language_override_is_en(): void
    {
        $company = $this->axenCompany();
        $issued = $this->issuedTaxableInvoice($company);

        $taxRecap = $issued->taxRecap;
        $taxRecap->forceFill(['document_language' => 'en'])->save();
        $taxRecap->loadMissing('invoice.client', 'invoice.company', 'invoice.taxSnapshot');

        $html = view('pdf.tax-recap', ['taxRecap' => $taxRecap])->render();

        $this->assertStringContainsString(__('documents.tax_recap_title', [], 'en'), $html);
        $this->assertStringNotContainsString(__('documents.tax_recap_title', [], 'id'), $html);
    }

    public function test_tax_recap_pdf_falls_back_to_company_default_document_language(): void
    {
        $company = $this->axenCompany();
        CompanySetting::create(['company_id' => $company->id, 'default_document_language' => 'en']);

        $issued = $this->issuedTaxableInvoice($company);
        $taxRecap = $issued->taxRecap;
        $taxRecap->loadMissing('invoice.client', 'invoice.company', 'invoice.taxSnapshot');

        $this->assertSame('en', $taxRecap->resolveDocumentLanguage());

        $html = view('pdf.tax-recap', ['taxRecap' => $taxRecap])->render();

        $this->assertStringContainsString(__('documents.tax_recap_title', [], 'en'), $html);
    }

    public function test_tax_recap_pdf_downloads_for_a_user_who_belongs_to_the_owning_company(): void
    {
        $company = $this->axenCompany();
        $user = User::factory()->create();
        $company->users()->attach($user, ['role' => 'owner']);

        $issued = $this->issuedTaxableInvoice($company);
        $taxRecap = $issued->taxRecap;

        $this->actingAs($user)
            ->get(route('tax-recaps.pdf', $taxRecap))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_tax_recap_pdf_is_forbidden_for_a_user_outside_the_owning_company(): void
    {
        $company = $this->axenCompany();
        $issued = $this->issuedTaxableInvoice($company);
        $taxRecap = $issued->taxRecap;

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('tax-recaps.pdf', $taxRecap))
            ->assertForbidden();
    }

    public function test_tax_recap_pdf_renders_the_parent_invoices_terms(): void
    {
        $company = $this->axenCompany();
        $issued = $this->issuedTaxableInvoice($company);
        $issued->forceFill(['terms' => '<p>Payment due within 30 days.</p>'])->save();

        $taxRecap = $issued->taxRecap;
        $taxRecap->loadMissing('invoice.client', 'invoice.company', 'invoice.taxSnapshot');

        $html = view('pdf.tax-recap', ['taxRecap' => $taxRecap])->render();

        $this->assertStringContainsString(__('documents.terms', [], 'id'), $html);
        $this->assertStringContainsString('Payment due within 30 days.', $html);
    }

    public function test_tax_recap_pdf_omits_the_terms_section_when_the_invoice_has_no_terms(): void
    {
        $company = $this->axenCompany();
        $issued = $this->issuedTaxableInvoice($company);

        $taxRecap = $issued->taxRecap;
        $taxRecap->loadMissing('invoice.client', 'invoice.company', 'invoice.taxSnapshot');

        $html = view('pdf.tax-recap', ['taxRecap' => $taxRecap])->render();

        $this->assertStringNotContainsString(__('documents.terms', [], 'id'), $html);
    }

    public function test_non_taxable_invoice_never_creates_a_tax_recap(): void
    {
        $company = Company::create(['name' => 'Karunia Abadi', 'slug' => 'karunia-abadi', 'code' => 'KA', 'currency_code' => 'IDR']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'pricing_mode' => PricingMode::Exclusive,
            'currency_code' => 'IDR',
        ]);

        InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Service', 'quantity' => 1, 'unit_cost' => 500000]);

        $issued = app(IssueInvoice::class)->issue($invoice, $this->owner($company));

        $this->assertNull($issued->taxRecap);
        $this->assertSame(0, TaxRecap::count());
    }
}
