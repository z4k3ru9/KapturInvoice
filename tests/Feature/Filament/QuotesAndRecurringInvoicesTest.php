<?php

namespace Tests\Feature\Filament;

use App\Enums\InvoiceType;
use App\Filament\Resources\Quotes\Pages\ListQuotes;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\RecurringInvoices\RecurringInvoiceResource;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceDuplicator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuotesAndRecurringInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    public function test_resource_index_and_create_pages_render(): void
    {
        foreach ([QuoteResource::class, RecurringInvoiceResource::class] as $resource) {
            $this->get($resource::getUrl('index', tenant: $this->company))->assertOk();
            $this->get($resource::getUrl('create', tenant: $this->company))->assertOk();
        }
    }

    public function test_quote_resource_only_lists_quotes(): void
    {
        Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'quote', 'status' => 'draft', 'number' => 'Q-1']);
        Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => 'draft', 'number' => 'INV-1']);

        $this->get(QuoteResource::getUrl('index', tenant: $this->company))
            ->assertOk()
            ->assertSee('Q-1')
            ->assertDontSee('INV-1');
    }

    public function test_converting_a_quote_creates_a_linked_invoice_with_cloned_items(): void
    {
        $quote = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'quote', 'status' => 'draft']);
        $quote->items()->create(['title' => 'Design work', 'quantity' => 2, 'unit_cost' => 50]);

        $invoice = app(InvoiceDuplicator::class)->convertQuoteToInvoice($quote->fresh(['items']));

        $this->assertSame(InvoiceType::Invoice, $invoice->type);
        $this->assertSame($quote->id, $invoice->converted_from_quote_id);
        $this->assertCount(1, $invoice->items);
        $this->assertSame('100.00', (string) $invoice->subtotal);
    }

    public function test_generating_a_recurring_invoice_creates_an_instance_and_stamps_the_template(): void
    {
        $template = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'is_recurring' => true,
            'recurring_frequency' => 'monthly',
        ]);
        $template->items()->create(['title' => 'Retainer', 'quantity' => 1, 'unit_cost' => 500]);

        $invoice = app(InvoiceDuplicator::class)->generateRecurringInstance($template->fresh(['items']));

        $this->assertFalse($invoice->is_recurring);
        $this->assertSame($template->id, $invoice->recurring_template_id);
        $this->assertSame('500.00', (string) $invoice->subtotal);
        $this->assertNotNull($template->fresh()->recurring_last_sent_at);
    }

    public function test_convert_to_invoice_table_action_works_from_the_quotes_list(): void
    {
        $quote = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'quote', 'status' => 'draft']);

        Livewire::test(ListQuotes::class)
            ->callTableAction('convertToInvoice', $quote);

        $this->assertDatabaseHas('invoices', ['converted_from_quote_id' => $quote->id]);
    }
}
