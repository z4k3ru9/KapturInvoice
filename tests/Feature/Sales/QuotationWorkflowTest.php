<?php

namespace Tests\Feature\Sales;

use App\Actions\Sales\AcceptQuotation;
use App\Actions\Sales\CreateSalesOrderFromQuotation;
use App\Actions\Sales\TransitionQuotationStatus;
use App\Enums\QuotationStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Required tests from docs/rebuild/specs/03-sales-and-job/Specs.md:
 * "Quote accepted with PO", "Quote accepted without PO", "Job cannot be
 * created from draft/rejected quote".
 */
class QuotationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuotation(Company $company, Client $client, QuotationStatus $status = QuotationStatus::Draft): Quotation
    {
        $quotation = Quotation::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'KA-QUO-2026090001',
            'status' => $status,
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

    private function sentQuotation(Company $company, Client $client): Quotation
    {
        $quotation = $this->makeQuotation($company, $client);

        app(TransitionQuotationStatus::class)->transition($quotation, QuotationStatus::Approved);
        app(TransitionQuotationStatus::class)->transition($quotation, QuotationStatus::Sent);

        return $quotation->fresh();
    }

    public function test_quote_accepted_with_a_supplied_customer_po(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        $quotation = $this->sentQuotation($company, $client);

        $accepted = app(AcceptQuotation::class)->accept($quotation, 'PO-12345', now());

        $this->assertTrue($accepted->status === QuotationStatus::Accepted);
        $this->assertSame('PO-12345', $accepted->customer_po_number);
        $this->assertFalse($accepted->customer_po_is_system_generated);
        $this->assertNotNull($accepted->accepted_at);
    }

    public function test_quote_accepted_without_a_customer_po_generates_a_customer_order_confirmation(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        $quotation = $this->sentQuotation($company, $client);

        $accepted = app(AcceptQuotation::class)->accept($quotation);

        $this->assertTrue($accepted->status === QuotationStatus::Accepted);
        $this->assertTrue($accepted->customer_po_is_system_generated);
        $this->assertStringContainsString('-COC-', $accepted->customer_po_number);
    }

    public function test_job_cannot_be_created_from_a_draft_quotation(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        $quotation = $this->makeQuotation($company, $client, QuotationStatus::Draft);

        $this->expectException(RuntimeException::class);

        app(CreateSalesOrderFromQuotation::class)->create($quotation);
    }

    public function test_job_cannot_be_created_from_a_rejected_quotation(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        $quotation = $this->sentQuotation($company, $client);

        app(TransitionQuotationStatus::class)->transition($quotation, QuotationStatus::Rejected);

        $this->expectException(RuntimeException::class);

        app(CreateSalesOrderFromQuotation::class)->create($quotation->fresh());
    }

    public function test_invalid_quotation_state_transition_is_denied(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        $quotation = $this->makeQuotation($company, $client, QuotationStatus::Draft);

        $this->expectException(RuntimeException::class);

        // Draft can only move to Approved or Cancelled — Sent is invalid
        // without passing through Approved first.
        app(TransitionQuotationStatus::class)->transition($quotation, QuotationStatus::Sent);
    }
}
