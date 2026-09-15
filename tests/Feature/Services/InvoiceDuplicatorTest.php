<?php

namespace Tests\Feature\Services;

use App\Enums\InvoiceType;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\InvoiceDuplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ported from tests/Feature/Filament/QuotesAndRecurringInvoicesTest.php
 * during the Filament-removal Phase B — these two tests exercise
 * App\Services\InvoiceDuplicator directly (no Filament UI involved).
 * tests/Feature/Documents/DocumentNumberingTest.php also calls these same
 * two methods, but only asserts the assigned document number — not item
 * cloning, subtotal computation, or the recurring template's
 * `recurring_last_sent_at` stamp asserted here — so these are kept rather
 * than dropped along with the rest of that file's genuinely
 * Filament-UI-only tests.
 */
class InvoiceDuplicatorTest extends TestCase
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
}
