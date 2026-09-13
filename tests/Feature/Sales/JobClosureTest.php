<?php

namespace Tests\Feature\Sales;

use App\Actions\Sales\CloseJobFinancially;
use App\Actions\Sales\CloseJobOperationally;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Required tests from docs/rebuild/specs/05-procurement-and-delivery/Specs.md:
 * "Financial closure blocked by unpaid customer invoice unless authorized
 * override" — plus FINALIZED-DECISIONS.md §4's Owner-only override rule
 * and App\Models\SalesOrder::isFullyClosed()'s two-sided gate.
 */
class JobClosureTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role]);

        return $user;
    }

    private function makeJob(): SalesOrder
    {
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'ACM-QUO-'.random_int(100000, 999999),
        ]);

        return SalesOrder::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'quotation_id' => $quotation->id,
            'number' => 'ACM-SO-'.random_int(100000, 999999),
            'approved_value' => 1000,
            'requires_handover' => false,
        ]);
    }

    private function makeInvoice(SalesOrder $salesOrder, float $total, float $balance): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'sales_order_id' => $salesOrder->id,
            'type' => InvoiceType::Invoice,
            'status' => InvoiceStatus::Issued,
            'number' => 'ACM-INV-'.random_int(100000, 999999),
        ]);

        $invoice->forceFill(['total' => $total, 'balance' => $balance, 'amount_paid' => $total - $balance])->save();

        return $invoice->fresh();
    }

    public function test_financial_closure_succeeds_immediately_with_no_outstanding_balance(): void
    {
        $job = $this->makeJob();
        $owner = $this->userWithRole('owner');
        $this->makeInvoice($job, 1000, 0);

        $closed = app(CloseJobFinancially::class)->close($job, $owner);

        $this->assertNotNull($closed->financial_closed_at);
    }

    public function test_financial_closure_is_blocked_by_an_outstanding_invoice_balance_without_override(): void
    {
        $job = $this->makeJob();
        $owner = $this->userWithRole('owner');
        $this->makeInvoice($job, 1000, 400);

        $this->expectException(RuntimeException::class);

        app(CloseJobFinancially::class)->close($job, $owner);
    }

    public function test_financial_closure_succeeds_with_an_owner_override_reason_and_summary(): void
    {
        $job = $this->makeJob();
        $owner = $this->userWithRole('owner');
        $this->makeInvoice($job, 1000, 400);

        $closed = app(CloseJobFinancially::class)->close(
            $job,
            $owner,
            override: true,
            overrideReason: 'Client agreed to settle next month',
            outstandingBalanceSummary: 'Outstanding 400.00 across 1 invoice',
        );

        $this->assertNotNull($closed->financial_closed_at);
    }

    public function test_financial_closure_override_is_denied_for_an_admin_actor(): void
    {
        $job = $this->makeJob();
        $admin = $this->userWithRole('admin');
        $this->makeInvoice($job, 1000, 400);

        $this->expectException(RuntimeException::class);

        app(CloseJobFinancially::class)->close(
            $job,
            $admin,
            override: true,
            overrideReason: 'Client agreed to settle next month',
            outstandingBalanceSummary: 'Outstanding 400.00 across 1 invoice',
        );
    }

    public function test_a_voided_invoices_balance_does_not_block_financial_closure(): void
    {
        $job = $this->makeJob();
        $owner = $this->userWithRole('owner');
        $this->makeInvoice($job, 1000, 0);
        $voided = $this->makeInvoice($job, 500, 500);
        $voided->forceFill(['status' => InvoiceStatus::Void])->save();

        $closed = app(CloseJobFinancially::class)->close($job, $owner);

        $this->assertNotNull($closed->financial_closed_at);
    }

    public function test_is_fully_closed_becomes_true_only_after_both_operational_and_financial_closure(): void
    {
        $job = $this->makeJob();
        $owner = $this->userWithRole('owner');
        $this->makeInvoice($job, 1000, 0);
        DeliveryOrder::create([
            'company_id' => $this->company->id,
            'sales_order_id' => $job->id,
            'number' => 'ACM-DO-'.random_int(100000, 999999),
        ]);

        $this->assertFalse($job->fresh()->isFullyClosed());

        app(CloseJobOperationally::class)->close($job->fresh());
        $this->assertFalse($job->fresh()->isFullyClosed());

        app(CloseJobFinancially::class)->close($job->fresh(), $owner);
        $this->assertTrue($job->fresh()->isFullyClosed());
    }
}
