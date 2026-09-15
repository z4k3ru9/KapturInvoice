<?php

namespace Tests\Feature\TallStack;

use App\Actions\Sales\AcceptQuotation;
use App\Actions\Sales\ApproveSalesOrder;
use App\Actions\Sales\CreateSalesOrderFromQuotation;
use App\Actions\Sales\TransitionQuotationStatus;
use App\Enums\MilestoneType;
use App\Enums\QuotationStatus;
use App\Livewire\TallStackInvoiceForm;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4 (KapturInvoice TallStackUI repair plan) — before this, an
 * invoice created through TallStackInvoiceForm was NEVER linked to a Job:
 * `Invoice::sales_order_id` is a real #[Fillable] column and
 * `SalesOrder::invoices()` is a real relation, but the form had no field
 * or mount()-time prefill for it at all. Confirmed live: Quotation ->
 * Accept -> Job created -> Approve -> create Invoice for the same client
 * -> the invoice never appeared on the job's own Billing tab, silently
 * breaking job-cost/margin reporting.
 *
 * This suite covers both new entry points added to close the gap: the
 * Job's own Billing tab "Create invoice" action (a `?sales_order_id=`
 * query-param prefill, mirroring the existing period_start/period_end
 * convention on App\Livewire\TallStackStatementOfAccount), and a manual
 * Job picker on the invoice form itself for the Invoices > Create entry
 * point (scoped to the selected client's own open jobs).
 */
class TallStackInvoiceJobLinkTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        app(Tenancy::class)->set($this->company);
    }

    /** Same accepted-quotation-to-approved-job pipeline as SalesOrderWorkflowTest::acceptedQuotationJob(), parameterized by client so cross-client scoping can be exercised. */
    private function approvedJobFor(Client $client): SalesOrder
    {
        $quotation = Quotation::create([
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'number' => 'ACM-QUO-'.$client->id.'-'.random_int(1000, 9999),
            'status' => QuotationStatus::Draft,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'title' => 'Widget',
            'quantity' => 2,
            'unit_cost' => 500,
            'line_total' => 1000,
        ]);
        $quotation->forceFill(['subtotal' => 1000, 'total' => 1000])->save();

        app(TransitionQuotationStatus::class)->transition($quotation, QuotationStatus::Approved);
        app(TransitionQuotationStatus::class)->transition($quotation, QuotationStatus::Sent);
        app(AcceptQuotation::class)->accept($quotation->fresh());

        $salesOrder = app(CreateSalesOrderFromQuotation::class)->create($quotation->fresh());

        $salesOrder->milestones()->create([
            'type' => MilestoneType::FullPayment,
            'description' => 'Full payment on completion',
            'amount' => $salesOrder->approved_value,
        ]);

        return app(ApproveSalesOrder::class)->approve($salesOrder);
    }

    /**
     * The exact end-to-end repro this phase's plan named: Quotation ->
     * Accept -> Job created -> Approve -> create an Invoice for the same
     * client via the Job's own "Create invoice" action -> the invoice now
     * appears on the job's Billing tab (App\Models\SalesOrder::invoices()).
     */
    public function test_creating_an_invoice_from_the_jobs_billing_tab_links_it_to_that_job(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Job Client']);
        $job = $this->approvedJobFor($client);

        $this->assertCount(0, $job->fresh()->invoices);

        // Simulates arriving at /tall/{company}/invoices/create?sales_order_id=...
        // via the Job page's own "Create invoice" link — Livewire's own
        // official test API for query-string prefill (LivewireManager::
        // withQueryParams()), not a hand-rolled request binding.
        $component = Livewire::withQueryParams(['sales_order_id' => (string) $job->id])
            ->test(TallStackInvoiceForm::class, ['company' => $this->company]);

        // The job link — and the client it belongs to — are prefilled
        // without any user interaction.
        $component->assertSet('sales_order_id', (string) $job->id)
            ->assertSet('client_id', (string) $client->id);

        $component->call('save');

        $invoice = Invoice::query()->where('company_id', $this->company->id)->sole();

        $this->assertSame($job->id, $invoice->sales_order_id);
        $this->assertCount(1, $job->fresh()->invoices);
        $this->assertSame($invoice->id, $job->fresh()->invoices->first()->id);
    }

    /** The manual picker covers Invoices > Create directly, not only the Job page's own entry point. */
    public function test_manually_picking_a_job_on_the_invoice_form_links_it(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Job Client']);
        $job = $this->approvedJobFor($client);

        $component = Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company])
            ->set('client_id', (string) $client->id)
            ->set('sales_order_id', (string) $job->id)
            ->call('save');

        $invoice = Invoice::query()->where('company_id', $this->company->id)->sole();

        $this->assertSame($job->id, $invoice->sales_order_id);
    }

    /** save()'s own backstop for a job/client mismatch — not only reachable via the UI's own dropdown scoping. */
    public function test_saving_with_a_job_that_belongs_to_a_different_client_is_rejected(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Invoice Client']);
        $otherClient = Client::create(['company_id' => $this->company->id, 'name' => 'Other Client']);
        $otherClientsJob = $this->approvedJobFor($otherClient);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company])
            ->set('client_id', (string) $client->id)
            ->set('sales_order_id', (string) $otherClientsJob->id)
            ->call('save')
            ->assertHasErrors(['sales_order_id']);

        $this->assertSame(0, Invoice::query()->where('company_id', $this->company->id)->count());
    }

    /** Guards against a stale/cross-tenant `?sales_order_id=` link silently attaching another company's job. */
    public function test_a_sales_order_id_query_param_from_another_company_is_ignored(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Job Client']);

        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co', 'code' => 'OTH', 'currency_code' => 'USD']);
        $otherClient = Client::create(['company_id' => $otherCompany->id, 'name' => 'Other Co Client']);
        app(Tenancy::class)->set($otherCompany);
        $foreignJob = $this->approvedJobFor($otherClient);
        app(Tenancy::class)->set($this->company);

        Livewire::withQueryParams(['sales_order_id' => (string) $foreignJob->id])
            ->test(TallStackInvoiceForm::class, ['company' => $this->company])
            ->assertSet('sales_order_id', null)
            ->assertSet('client_id', null);
    }

    /** A stale job selection is cleared, not silently carried over, once the invoice is repointed at a different client. */
    public function test_changing_the_client_clears_a_previously_selected_job_for_the_old_client(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'First Client']);
        $otherClient = Client::create(['company_id' => $this->company->id, 'name' => 'Second Client']);
        $job = $this->approvedJobFor($client);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company])
            ->set('client_id', (string) $client->id)
            ->set('sales_order_id', (string) $job->id)
            ->assertSet('sales_order_id', (string) $job->id)
            ->set('client_id', (string) $otherClient->id)
            ->assertSet('sales_order_id', null);
    }
}
