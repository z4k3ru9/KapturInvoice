<?php

namespace Tests\Feature\Filament;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Filament\Resources\Credits\Tables\CreditsTable;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Filament\Resources\Quotations\Tables\QuotationsTable;
use App\Filament\Resources\Quotes\Tables\QuotesTable;
use App\Filament\Resources\SalesOrders\Tables\SalesOrdersTable;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/REFACTOR_PLAN.md drift audit: Filament's stock ForceDeleteBulkAction
 * only hides itself while the Trashed filter isn't set — once switched to
 * "With Trashed"/"Only Trashed", any selected row (regardless of status)
 * got `forceDelete()`'d directly, contradicting FINALIZED-DECISIONS.md §2's
 * "never physically deleted, including by an Owner." Each table now
 * exposes a static `isSafeToForceDelete()` guard, shared by both the bulk
 * action and the page-level single ForceDeleteAction — this proves the
 * guard itself, independent of the Livewire bulk-action UI.
 */
class ForceDeleteGuardsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
    }

    public function test_a_draft_invoice_with_no_history_is_safe_to_force_delete(): void
    {
        $invoice = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => 'draft']);

        $this->assertTrue(InvoicesTable::isSafeToForceDelete($invoice));
    }

    public function test_an_issued_invoice_is_never_safe_to_force_delete(): void
    {
        $invoice = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => InvoiceStatus::Issued]);

        $this->assertFalse(InvoicesTable::isSafeToForceDelete($invoice));
    }

    public function test_a_draft_invoice_with_a_recorded_payment_is_not_safe_to_force_delete(): void
    {
        $invoice = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => 'draft']);
        Payment::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'invoice_id' => $invoice->id, 'amount' => 10, 'status' => PaymentStatus::Pending]);

        $this->assertFalse(InvoicesTable::isSafeToForceDelete($invoice->fresh()));
    }

    public function test_a_pending_payment_with_no_history_is_safe_to_force_delete(): void
    {
        $payment = Payment::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'amount' => 10, 'status' => PaymentStatus::Pending]);

        $this->assertTrue(PaymentsTable::isSafeToForceDelete($payment));
    }

    public function test_a_verified_payment_is_never_safe_to_force_delete(): void
    {
        $payment = Payment::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'amount' => 10, 'status' => PaymentStatus::Verified]);

        $this->assertFalse(PaymentsTable::isSafeToForceDelete($payment));
    }

    public function test_a_draft_quotation_with_no_job_is_safe_to_force_delete(): void
    {
        $quotation = Quotation::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'number' => 'ACM-QUO-1', 'status' => QuotationStatus::Draft]);

        $this->assertTrue(QuotationsTable::isSafeToForceDelete($quotation));
    }

    public function test_a_quotation_with_a_job_created_from_it_is_never_safe_to_force_delete(): void
    {
        $quotation = Quotation::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'number' => 'ACM-QUO-2', 'status' => QuotationStatus::Draft]);
        SalesOrder::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'quotation_id' => $quotation->id, 'number' => 'ACM-SO-1', 'approved_value' => 0]);

        $this->assertFalse(QuotationsTable::isSafeToForceDelete($quotation->fresh()));
    }

    public function test_a_draft_quote_with_no_converted_invoice_is_safe_to_force_delete(): void
    {
        $quote = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'quote', 'status' => 'draft']);

        $this->assertTrue(QuotesTable::isSafeToForceDelete($quote));
    }

    public function test_a_quote_already_converted_to_an_invoice_is_never_safe_to_force_delete(): void
    {
        $quote = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'quote', 'status' => 'draft']);
        Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => 'draft', 'converted_from_quote_id' => $quote->id]);

        $this->assertFalse(QuotesTable::isSafeToForceDelete($quote->fresh()));
    }

    public function test_a_draft_job_with_no_activity_is_safe_to_force_delete(): void
    {
        $quotation = Quotation::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'number' => 'ACM-QUO-3']);
        $job = SalesOrder::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'quotation_id' => $quotation->id, 'number' => 'ACM-SO-2', 'approved_value' => 0, 'status' => SalesOrderStatus::Draft]);

        $this->assertTrue(SalesOrdersTable::isSafeToForceDelete($job));
    }

    public function test_a_job_with_an_invoice_is_never_safe_to_force_delete(): void
    {
        $quotation = Quotation::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'number' => 'ACM-QUO-4']);
        $job = SalesOrder::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'quotation_id' => $quotation->id, 'number' => 'ACM-SO-3', 'approved_value' => 0, 'status' => SalesOrderStatus::Draft]);
        Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => 'draft', 'sales_order_id' => $job->id]);

        $this->assertFalse(SalesOrdersTable::isSafeToForceDelete($job->fresh()));
    }

    public function test_a_job_that_is_not_draft_is_never_safe_to_force_delete(): void
    {
        $quotation = Quotation::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'number' => 'ACM-QUO-5']);
        $job = SalesOrder::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'quotation_id' => $quotation->id, 'number' => 'ACM-SO-4', 'approved_value' => 0, 'status' => SalesOrderStatus::Approved]);

        $this->assertFalse(SalesOrdersTable::isSafeToForceDelete($job));
    }

    public function test_an_unapplied_credit_is_safe_to_force_delete(): void
    {
        $credit = Credit::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'amount' => 100]);

        $this->assertTrue(CreditsTable::isSafeToForceDelete($credit));
    }

    public function test_a_partially_applied_credit_is_never_safe_to_force_delete(): void
    {
        $credit = Credit::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'amount' => 100]);
        // 'balance' is deliberately not mass-assignable (Credit::booted()
        // is the only normal writer) — forceFill simulates a real
        // application of the credit for this guard test.
        $credit->forceFill(['balance' => 40])->save();

        $this->assertFalse(CreditsTable::isSafeToForceDelete($credit->fresh()));
    }
}
