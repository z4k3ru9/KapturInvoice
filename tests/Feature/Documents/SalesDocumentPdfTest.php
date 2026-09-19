<?php

namespace Tests\Feature\Documents;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Http\Controllers\QuotationPdfController;
use App\Http\Controllers\ReceiptPdfController;
use App\Http\Controllers\SalesOrderPdfController;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Services\QuotationTotalsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * PDF export for the Phase 06B launch document set — Quotation (also the
 * printed Customer Order Confirmation), Sales Order, and Receipt — see
 * docs/rebuild/specs/06b-ux-browser-soa/Specs.md "Required launch document
 * coverage" and the same "outside the legacy admin panel, check tenant
 * explicitly" pattern as tests/Feature/PdfExportTest.php.
 *
 * This task's instructions deliberately keep `routes/web.php` untouched
 * (a concurrent agent owns it) — the three real route lines to add there
 * are named in the final report. These routes are registered here, in
 * `setUp()`, only so this test file can exercise the controllers under
 * the exact same URI-name/middleware shape `invoices.pdf` already uses.
 */
class SalesDocumentPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // routes/web.php's real routes are wrapped in the 'web' middleware
        // group (which carries SubstituteBindings) by the framework's own
        // route loading — routes registered here at runtime are not, so
        // it's added explicitly or implicit route-model-binding silently
        // resolves an empty model instead of querying the database.
        Route::get('/quotations/{quotation}/pdf', QuotationPdfController::class)
            ->middleware(['web', 'auth'])
            ->name('quotations.pdf');
        Route::get('/sales-orders/{salesOrder}/pdf', SalesOrderPdfController::class)
            ->middleware(['web', 'auth'])
            ->name('sales-orders.pdf');
        Route::get('/receipts/{receipt}/pdf', ReceiptPdfController::class)
            ->middleware(['web', 'auth'])
            ->name('receipts.pdf');

        // Route::name() (above) only sets the name on the Route instance
        // already added to the collection — the name look-up table is
        // built once, from routes/web.php, at framework boot and isn't
        // refreshed automatically afterward. Rebuild it so route()/
        // route-model-bound test requests below can resolve these names.
        Route::getRoutes()->refreshNameLookups();
    }

    private function makeCompanyAndClient(): array
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);

        return [$company, $client];
    }

    private function makeUser(Company $company): User
    {
        $user = User::factory()->create();
        $company->users()->attach($user, ['role' => 'owner']);

        return $user;
    }

    private function makeQuotation(Company $company, Client $client, array $overrides = []): Quotation
    {
        $quotation = Quotation::create(array_merge([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'QUO-0001',
            'status' => QuotationStatus::Draft,
            'quotation_date' => '2026-09-01',
            'valid_until' => '2026-10-01',
        ], $overrides));

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'title' => 'Widget',
            'quantity' => 2,
            'unit_cost' => 500,
            'line_total' => 1000,
        ]);

        $quotation->forceFill(['subtotal' => 1000, 'total' => 1000])->save();

        return $quotation->fresh();
    }

    private function makeSalesOrder(Company $company, Client $client, Quotation $quotation, array $overrides = []): SalesOrder
    {
        $salesOrder = SalesOrder::create(array_merge([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'number' => 'JOB-0001',
            'status' => SalesOrderStatus::Draft,
            'approved_value' => 1000,
            'source_snapshot' => [
                'quotation_number' => $quotation->number,
                'subtotal' => '1000.00',
                'discount' => '0.00',
                'discount_is_percentage' => false,
                'total' => '1000.00',
            ],
        ], $overrides));

        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'title' => 'Widget',
            'quantity' => 2,
            'unit_cost' => 500,
            'line_total' => 1000,
            'sort_order' => 1,
        ]);

        return $salesOrder->fresh();
    }

    private function makeReceipt(Company $company, Client $client, array $overrides = []): Receipt
    {
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'number' => 'INV-0001',
        ]);

        $payment = Payment::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'method' => PaymentMethod::BankTransfer->value,
            'amount' => 500,
            'currency_code' => 'USD',
            'payment_date' => '2026-09-05',
            'proof_path' => 'proofs/one.pdf',
            'reference' => 'REF-001',
            'status' => PaymentStatus::Verified,
        ]);

        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'is_active' => true,
        ]);

        $receipt = Receipt::create(array_merge([
            'company_id' => $company->id,
            'payment_id' => $payment->id,
            'number' => 'RCP-0001',
            'issued_at' => '2026-09-06',
        ], $overrides));

        return $receipt->fresh();
    }

    // --- Quotation -----------------------------------------------------

    public function test_quotation_pdf_downloads_for_a_user_who_belongs_to_the_owning_company(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $user = $this->makeUser($company);
        $quotation = $this->makeQuotation($company, $client);

        $this->actingAs($user)
            ->get(route('quotations.pdf', $quotation))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_quotation_pdf_is_forbidden_for_a_user_outside_the_owning_company(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $quotation = $this->makeQuotation($company, $client);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('quotations.pdf', $quotation))
            ->assertForbidden();
    }

    public function test_quotation_pdf_renders_bahasa_indonesia_labels_by_default(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $quotation = $this->makeQuotation($company, $client);
        $quotation->loadMissing('client', 'company', 'items');

        $html = view('pdf.quotation', ['quotation' => $quotation])->render();

        $this->assertStringContainsString(__('documents.subtotal', [], 'id'), $html);
        $this->assertStringContainsString(__('documents.type_quotation', [], 'id'), $html);
    }

    public function test_quotation_pdf_renders_english_labels_when_document_language_override_is_en(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $quotation = $this->makeQuotation($company, $client, ['document_language' => 'en']);
        $quotation->loadMissing('client', 'company', 'items');

        $html = view('pdf.quotation', ['quotation' => $quotation])->render();

        $this->assertStringContainsString(__('documents.subtotal', [], 'en'), $html);
        $this->assertStringContainsString(__('documents.type_quotation', [], 'en'), $html);
    }

    /**
     * "Do not invent a customer-issued PO. The Customer Order Confirmation
     * remains an internal record when the customer accepts without
     * supplying a PO." — an accepted quotation with a system-generated PO
     * number prints as the Customer Order Confirmation, showing that
     * number prominently, and NOT as a plain Quotation.
     */
    public function test_accepted_quotation_with_system_generated_po_prints_as_a_customer_order_confirmation(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $quotation = $this->makeQuotation($company, $client, [
            'status' => QuotationStatus::Accepted,
            'customer_po_number' => 'COC-0001',
            'customer_po_is_system_generated' => true,
            'accepted_at' => now(),
        ]);
        $quotation->loadMissing('client', 'company', 'items');

        $html = view('pdf.quotation', ['quotation' => $quotation])->render();

        $this->assertStringContainsString(__('documents.type_coc'), $html);
        $this->assertStringContainsString(__('documents.coc_no'), $html);
        $this->assertStringContainsString('COC-0001', $html);
        $this->assertStringNotContainsString(__('documents.type_quotation'), $html);
    }

    /**
     * An accepted quotation with a genuinely customer-supplied PO number
     * (not system-generated) is never presented as a Customer Order
     * Confirmation — it keeps printing as a plain Quotation.
     */
    public function test_accepted_quotation_with_a_real_customer_po_still_prints_as_a_quotation(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $quotation = $this->makeQuotation($company, $client, [
            'status' => QuotationStatus::Accepted,
            'customer_po_number' => 'PO-999',
            'customer_po_is_system_generated' => false,
            'accepted_at' => now(),
        ]);
        $quotation->loadMissing('client', 'company', 'items');

        $html = view('pdf.quotation', ['quotation' => $quotation])->render();

        $this->assertStringContainsString(__('documents.type_quotation'), $html);
        $this->assertStringNotContainsString(__('documents.type_coc'), $html);
    }

    /**
     * Mirrors the Invoice PDF fix ("Discount total line in summary: compute
     * subtotal - total from stored columns") — a percentage discount also shows the computed
     * nominal amount, derived from App\Services\QuotationTotalsCalculator's
     * own already-persisted subtotal/total (never a fresh calculation).
     */
    public function test_quotation_pdf_shows_nominal_discount_amount_alongside_a_percentage_discount(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $quotation = Quotation::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'QUO-0002',
            'status' => QuotationStatus::Draft,
            'quotation_date' => '2026-09-01',
            'valid_until' => '2026-10-01',
            'discount' => 10,
            'discount_is_percentage' => true,
        ]);
        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'title' => 'Widget',
            'quantity' => 2,
            'unit_cost' => 500,
            'line_total' => 1000,
        ]);
        app(QuotationTotalsCalculator::class)->recalculate($quotation);
        $quotation = $quotation->fresh();
        $quotation->loadMissing('client', 'company', 'items');

        $html = view('pdf.quotation', ['quotation' => $quotation])->render();

        $this->assertStringContainsString('(-USD 100.00)', $html);
    }

    /**
     * Universal PDF polish (this session): every line-items table gets a
     * subtle alternating row background so it reads as a striped table.
     */
    public function test_quotation_pdf_items_table_has_striped_row_css(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $quotation = $this->makeQuotation($company, $client);
        $quotation->loadMissing('client', 'company', 'items');

        $html = view('pdf.quotation', ['quotation' => $quotation])->render();

        $this->assertStringContainsString('table.items tbody tr:nth-child(even)', $html);
    }

    // --- Sales Order -----------------------------------------------------

    public function test_sales_order_pdf_downloads_for_a_user_who_belongs_to_the_owning_company(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $user = $this->makeUser($company);
        $quotation = $this->makeQuotation($company, $client);
        $salesOrder = $this->makeSalesOrder($company, $client, $quotation);

        $this->actingAs($user)
            ->get(route('sales-orders.pdf', $salesOrder))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_sales_order_pdf_is_forbidden_for_a_user_outside_the_owning_company(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $quotation = $this->makeQuotation($company, $client);
        $salesOrder = $this->makeSalesOrder($company, $client, $quotation);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('sales-orders.pdf', $salesOrder))
            ->assertForbidden();
    }

    public function test_sales_order_pdf_renders_bahasa_indonesia_labels_by_default(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $quotation = $this->makeQuotation($company, $client);
        $salesOrder = $this->makeSalesOrder($company, $client, $quotation);
        $salesOrder->loadMissing('client', 'company', 'items', 'quotation');

        $html = view('pdf.sales-order', ['salesOrder' => $salesOrder])->render();

        $this->assertStringContainsString(__('documents.type_sales_order', [], 'id'), $html);
        $this->assertStringContainsString(__('documents.sales_order_approved_value', [], 'id'), $html);
    }

    public function test_sales_order_pdf_renders_english_labels_when_document_language_override_is_en(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $quotation = $this->makeQuotation($company, $client);
        $salesOrder = $this->makeSalesOrder($company, $client, $quotation, ['document_language' => 'en']);
        $salesOrder->loadMissing('client', 'company', 'items', 'quotation');

        $html = view('pdf.sales-order', ['salesOrder' => $salesOrder])->render();

        $this->assertStringContainsString(__('documents.type_sales_order', [], 'en'), $html);
        $this->assertStringContainsString(__('documents.sales_order_approved_value', [], 'en'), $html);
    }

    // --- Receipt -----------------------------------------------------

    public function test_receipt_pdf_downloads_for_a_user_who_belongs_to_the_owning_company(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $user = $this->makeUser($company);
        $receipt = $this->makeReceipt($company, $client);

        $this->actingAs($user)
            ->get(route('receipts.pdf', $receipt))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_receipt_pdf_is_forbidden_for_a_user_outside_the_owning_company(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $receipt = $this->makeReceipt($company, $client);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('receipts.pdf', $receipt))
            ->assertForbidden();
    }

    public function test_receipt_pdf_renders_bahasa_indonesia_labels_by_default_and_lists_the_allocated_invoice(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $receipt = $this->makeReceipt($company, $client);
        $receipt->loadMissing('company', 'payment.client', 'payment.allocations.invoice');

        $html = view('pdf.receipt', ['receipt' => $receipt])->render();

        $this->assertStringContainsString(__('documents.receipt_amount', [], 'id'), $html);
        $this->assertStringContainsString(__('documents.type_receipt', [], 'id'), $html);
        $this->assertStringContainsString('INV-0001', $html);
    }

    public function test_receipt_pdf_renders_english_labels_when_document_language_override_is_en(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        $receipt = $this->makeReceipt($company, $client, ['document_language' => 'en']);
        $receipt->loadMissing('company', 'payment.client', 'payment.allocations.invoice');

        $html = view('pdf.receipt', ['receipt' => $receipt])->render();

        $this->assertStringContainsString(__('documents.receipt_amount', [], 'en'), $html);
        $this->assertStringContainsString(__('documents.type_receipt', [], 'en'), $html);
    }

    /**
     * No CompanySetting row at all should still default to Bahasa
     * Indonesia — see resolveDocumentLanguage() on each of the three
     * models, mirroring Invoice::resolveDocumentLanguage().
     */
    public function test_default_document_language_falls_back_to_company_setting_then_bahasa(): void
    {
        [$company, $client] = $this->makeCompanyAndClient();
        CompanySetting::create(['company_id' => $company->id, 'default_document_language' => 'en']);
        $quotation = $this->makeQuotation($company, $client);

        $this->assertSame('en', $quotation->resolveDocumentLanguage());

        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        $otherClient = Client::create(['company_id' => $otherCompany->id, 'name' => 'Other Client']);
        $noSettingQuotation = $this->makeQuotation($otherCompany, $otherClient, ['number' => 'QUO-0002']);

        $this->assertSame('id', $noSettingQuotation->resolveDocumentLanguage());
    }
}
