<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Credits\Pages\CreateCredit;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Quotes\Pages\CreateQuote;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\DocumentNumberGenerator;
use App\Services\InvoiceDuplicator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * App\Services\DocumentNumberGenerator closes the gap flagged in
 * docs/filament-admin-layout-design.md §2.1/§3.2 — invoice/quote/credit
 * numbers are now actually assigned from the company's sequence, not left
 * as a manual text field with no backing implementation.
 */
class DocumentNumberingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create([
            'name' => 'Acme',
            'slug' => 'acme',
            'currency_code' => 'USD',
            'invoice_prefix' => 'INV-',
            'invoice_next_number' => 1,
            'quote_prefix' => 'QUO-',
            'quote_next_number' => 1,
            'credit_prefix' => 'CRE-',
            'credit_next_number' => 1,
        ]);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    public function test_generator_assigns_and_increments_each_sequence_independently(): void
    {
        $generator = app(DocumentNumberGenerator::class);

        $this->assertSame('INV-0001', $generator->next($this->company, 'invoice'));
        $this->assertSame('INV-0002', $generator->next($this->company->fresh(), 'invoice'));
        $this->assertSame('QUO-0001', $generator->next($this->company->fresh(), 'quote'));
        $this->assertSame('CRE-0001', $generator->next($this->company->fresh(), 'credit'));

        $this->assertSame(3, $this->company->fresh()->invoice_next_number);
        $this->assertSame(2, $this->company->fresh()->quote_next_number);
        $this->assertSame(2, $this->company->fresh()->credit_next_number);
    }

    public function test_creating_an_invoice_with_a_blank_number_auto_assigns_one(): void
    {
        Livewire::test(CreateInvoice::class, ['tenant' => $this->company])
            ->fillForm(['client_id' => $this->client->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('invoices', ['company_id' => $this->company->id, 'number' => 'INV-0001']);
        $this->assertSame(2, $this->company->fresh()->invoice_next_number);
    }

    public function test_creating_an_invoice_with_an_explicit_number_does_not_consume_the_sequence(): void
    {
        Livewire::test(CreateInvoice::class, ['tenant' => $this->company])
            ->fillForm(['client_id' => $this->client->id, 'number' => 'MANUAL-1'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('invoices', ['company_id' => $this->company->id, 'number' => 'MANUAL-1']);
        $this->assertSame(1, $this->company->fresh()->invoice_next_number);
    }

    public function test_creating_a_quote_assigns_from_the_quote_sequence(): void
    {
        Livewire::test(CreateQuote::class, ['tenant' => $this->company])
            ->fillForm(['client_id' => $this->client->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('invoices', ['company_id' => $this->company->id, 'type' => 'quote', 'number' => 'QUO-0001']);
        $this->assertSame(2, $this->company->fresh()->quote_next_number);
        $this->assertSame(1, $this->company->fresh()->invoice_next_number, 'quote creation must not touch the invoice sequence');
    }

    public function test_creating_a_credit_assigns_from_the_credit_sequence(): void
    {
        Livewire::test(CreateCredit::class, ['tenant' => $this->company])
            ->fillForm(['client_id' => $this->client->id, 'amount' => 50])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('credits', ['company_id' => $this->company->id, 'number' => 'CRE-0001']);
        $this->assertSame(2, $this->company->fresh()->credit_next_number);
    }

    public function test_converting_a_quote_assigns_a_real_invoice_number(): void
    {
        $quote = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'quote', 'status' => 'draft']);

        $invoice = app(InvoiceDuplicator::class)->convertQuoteToInvoice($quote->fresh(['items']));

        $this->assertSame('INV-0001', $invoice->number);
        $this->assertSame(2, $this->company->fresh()->invoice_next_number);
    }

    public function test_generating_a_recurring_instance_assigns_a_real_invoice_number(): void
    {
        $template = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => 'draft', 'is_recurring' => true]);

        $invoice = app(InvoiceDuplicator::class)->generateRecurringInstance($template->fresh(['items']));

        $this->assertSame('INV-0001', $invoice->number);
        $this->assertSame(2, $this->company->fresh()->invoice_next_number);
    }
}
