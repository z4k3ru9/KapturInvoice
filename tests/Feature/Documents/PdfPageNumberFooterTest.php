<?php

namespace Tests\Feature\Documents;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Support\Pdf\PageNumberFooter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 8 (PDF pagination footer). dompdf's CSS `@page { @bottom-right {
 * content: counter(page) ... } }` margin-box syntax is not implemented by
 * the installed dompdf (confirmed by reading
 * vendor/dompdf/dompdf/src/Css/Stylesheet.php and by an empirical render
 * that produced no visible footer text on any page) — the real mechanism
 * is App\Support\Pdf\PageNumberFooter, which uses dompdf's
 * Canvas::page_script() PHP API instead. See that class's docblock and
 * resources/views/pdf/partials/page-footer.blade.php for the full story.
 */
class PdfPageNumberFooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_pdf_response_is_genuinely_multi_page_when_content_overflows_one_page(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $company->users()->attach($user, ['role' => 'owner']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $invoice = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0001']);

        for ($i = 1; $i <= 60; $i++) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'title' => "Line item #{$i}",
                'description' => 'Padding text so each row is tall enough to force a real page break.',
                'quantity' => 1,
                'unit_cost' => 100,
                'line_total' => 100,
            ]);
        }

        $response = $this->actingAs($user)
            ->get(route('invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Real PDF page objects ("/Type /Page", never the parent "/Type
        // /Pages" node) live in plain object dictionaries, not inside the
        // (compressed) content streams — so this is readable regardless
        // of dompdf's default stream compression. A single-page document
        // has exactly one; this 60-item invoice must overflow to several.
        $pageObjectCount = preg_match_all('/\/Type\s*\/Page(?!s)/', $response->getContent());

        $this->assertGreaterThanOrEqual(3, $pageObjectCount, 'Expected the 60-item invoice to render as multiple real PDF pages.');
    }

    public function test_footer_text_increments_correctly_across_every_page_in_bahasa_indonesia(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        // document_language left null -> defaults to 'id' (Bahasa Indonesia).
        $invoice = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0002']);

        for ($i = 1; $i <= 60; $i++) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'title' => "Line item #{$i}",
                'description' => 'Padding text so each row is tall enough to force a real page break.',
                'quantity' => 1,
                'unit_cost' => 100,
                'line_total' => 100,
            ]);
        }

        $pdf = PageNumberFooter::apply(Pdf::loadView('pdf.invoice', ['invoice' => $invoice->fresh()]));

        // Uncompressed output (dompdf's own debug/no-compress mode) keeps
        // the page content streams as plain text, so the exact footer
        // string drawn on each page can be asserted directly rather than
        // only inferred from page-object counts.
        $raw = $pdf->output(['compress' => false]);

        $pageCount = preg_match_all('/\/Type\s*\/Page(?!s)/', $raw);
        $this->assertGreaterThanOrEqual(3, $pageCount);

        for ($page = 1; $page <= $pageCount; $page++) {
            $this->assertStringContainsString(
                "Halaman {$page} dari {$pageCount}",
                $raw,
                "Expected the page-{$page} footer text to be present."
            );
        }
    }

    public function test_footer_text_is_localized_to_english_when_the_document_requests_it(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'number' => 'INV-0003',
            'document_language' => 'en',
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Consulting',
            'quantity' => 1,
            'unit_cost' => 100,
            'line_total' => 100,
        ]);

        $pdf = PageNumberFooter::apply(Pdf::loadView('pdf.invoice', ['invoice' => $invoice->fresh()]));
        $raw = $pdf->output(['compress' => false]);

        $this->assertStringContainsString('Page 1 of 1', $raw);
    }
}
