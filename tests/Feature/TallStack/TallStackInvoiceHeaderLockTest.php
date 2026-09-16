<?php

namespace Tests\Feature\TallStack;

use App\Actions\Billing\IssueInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Livewire\TallStackInvoiceForm;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for a real gap: App\Livewire\TallStackInvoiceForm::
 * save() used to write header fields (client_id/sales_order_id/
 * pricing_mode/etc.) unconditionally, even once an invoice had already
 * been Issued — silently bypassing the Phase 04 immutability rule
 * CLAUDE.md documents ("once an invoice is Issued its header/total/tax
 * snapshot is immutable ... corrections must go through
 * App\Actions\Billing\AmendIssuedInvoice or
 * App\Actions\Billing\VoidAndReissueInvoice"). App\Enums\InvoiceStatus::
 * isLocked() now gates this directly inside save().
 */
class TallStackInvoiceHeaderLockTest extends TestCase
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

    private function invoiceWithItem(Client $client): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'pricing_mode' => 'exclusive',
            'currency_code' => 'USD',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'title' => 'Service',
            'quantity' => 1,
            'unit_cost' => 100,
        ]);

        return $invoice->fresh(['items']);
    }

    /** A bare Draft job belonging to $client — only used here as a valid, existing sales_order_id FK target, not exercising the real quotation-acceptance lifecycle. */
    private function jobFor(Client $client): SalesOrder
    {
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => 'ACM-QUO-'.$client->id,
            'status' => QuotationStatus::Draft,
        ]);

        return SalesOrder::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'status' => SalesOrderStatus::Draft,
        ]);
    }

    public function test_editing_header_fields_on_an_issued_invoice_is_rejected(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Original Client']);
        $otherClient = Client::create(['company_id' => $this->company->id, 'name' => 'Other Client']);
        $job = $this->jobFor($client);

        $invoice = $this->invoiceWithItem($client);
        $issued = app(IssueInvoice::class)->issue($invoice, auth()->user());

        $this->assertSame(InvoiceStatus::Issued, $issued->status);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $issued])
            ->set('client_id', (string) $otherClient->id)
            ->set('sales_order_id', (string) $job->id)
            ->set('pricing_mode', 'inclusive')
            ->call('save')
            ->assertDispatched('ts-ui:toast', type: 'error', title: 'Could not save invoice');

        $issued->refresh();
        $this->assertSame($client->id, $issued->client_id);
        $this->assertNull($issued->sales_order_id);
        $this->assertSame('exclusive', $issued->pricing_mode->value);
    }

    /** Control case: a Draft invoice's header fields remain freely editable through the same form/method — the guard must not overreach. */
    public function test_editing_header_fields_on_a_draft_invoice_still_works(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Original Client']);
        $job = $this->jobFor($client);

        $draft = $this->invoiceWithItem($client);
        $this->assertSame(InvoiceStatus::Draft, $draft->status);

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $draft])
            ->set('sales_order_id', (string) $job->id)
            ->set('pricing_mode', 'inclusive')
            ->call('save')
            ->assertHasNoErrors();

        $draft->refresh();
        $this->assertSame($job->id, $draft->sales_order_id);
        $this->assertSame('inclusive', $draft->pricing_mode->value);
    }
}
