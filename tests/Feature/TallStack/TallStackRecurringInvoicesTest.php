<?php

namespace Tests\Feature\TallStack;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Livewire\TallStackRecurringInvoiceForm;
use App\Livewire\TallStackRecurringInvoices;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Proves the TallStackUI Recurring Invoices register/detail pages reuse
 * the exact same App\Models\Invoice (`is_recurring = true`) scope and
 * App\Services\InvoiceDuplicator::generateRecurringInstance() action the
 * Filament RecurringInvoicesTable's own row actions use — a
 * presentation-layer swap only — and that both pages re-check company
 * ownership/`is_recurring` explicitly (this route sits outside Filament's
 * own tenant-scoped binding), same reasoning as
 * tests/Feature/TallStack/TallStackQuotationSendTest.php.
 */
class TallStackRecurringInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->actingAs($this->user);
    }

    private function template(array $overrides = []): Invoice
    {
        $invoice = Invoice::create(array_merge([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => InvoiceType::Invoice,
            'status' => InvoiceStatus::Draft,
            'number' => 'ACM-INV-0001',
            'is_recurring' => true,
            'recurring_frequency' => 'monthly',
            'recurring_start_date' => now()->subMonth()->toDateString(),
        ], $overrides));
        $invoice->forceFill(['subtotal' => 500, 'total' => 500])->save();

        return $invoice->fresh();
    }

    public function test_the_register_only_lists_recurring_templates_for_the_current_company(): void
    {
        $template = $this->template();

        // A plain, non-recurring invoice in the same company must never
        // show up on this register.
        Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => InvoiceType::Invoice,
            'status' => InvoiceStatus::Draft,
            'number' => 'ACM-INV-0002',
            'is_recurring' => false,
        ]);

        Livewire::test(TallStackRecurringInvoices::class, ['company' => $this->company])
            ->assertSee($template->number)
            ->assertDontSee('ACM-INV-0002');
    }

    public function test_generate_now_creates_a_real_invoice_linked_to_the_template(): void
    {
        $template = $this->template();
        InvoiceItem::create([
            'invoice_id' => $template->id,
            'title' => 'Monthly retainer',
            'quantity' => 1,
            'unit_cost' => 500,
            'sort_order' => 1,
        ]);

        Livewire::test(TallStackRecurringInvoices::class, ['company' => $this->company])
            ->call('generateNow', $template->id);

        $generated = Invoice::query()->where('recurring_template_id', $template->id)->first();

        $this->assertNotNull($generated);
        $this->assertFalse($generated->is_recurring);
        $this->assertSame(500.0, (float) $generated->total);
        $this->assertNotNull($template->fresh()->recurring_last_sent_at);
    }

    public function test_generate_now_ignores_a_template_belonging_to_another_company(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        $otherClient = Client::create(['company_id' => $otherCompany->id, 'name' => 'Other Client']);
        $foreignTemplate = Invoice::create([
            'company_id' => $otherCompany->id,
            'client_id' => $otherClient->id,
            'type' => InvoiceType::Invoice,
            'status' => InvoiceStatus::Draft,
            'number' => 'OTH-INV-0001',
            'is_recurring' => true,
            'recurring_frequency' => 'monthly',
        ]);

        Livewire::test(TallStackRecurringInvoices::class, ['company' => $this->company])
            ->call('generateNow', $foreignTemplate->id);

        $this->assertSame(0, Invoice::query()->where('recurring_template_id', $foreignTemplate->id)->count());
    }

    public function test_the_edit_page_404s_for_a_template_belonging_to_another_company(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        $otherClient = Client::create(['company_id' => $otherCompany->id, 'name' => 'Other Client']);
        $foreignTemplate = Invoice::create([
            'company_id' => $otherCompany->id,
            'client_id' => $otherClient->id,
            'type' => InvoiceType::Invoice,
            'status' => InvoiceStatus::Draft,
            'number' => 'OTH-INV-0002',
            'is_recurring' => true,
            'recurring_frequency' => 'monthly',
        ]);

        Livewire::test(TallStackRecurringInvoiceForm::class, ['company' => $this->company, 'invoice' => $foreignTemplate])
            ->assertStatus(404);
    }

    public function test_the_edit_page_404s_for_a_plain_non_recurring_invoice(): void
    {
        $plain = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => InvoiceType::Invoice,
            'status' => InvoiceStatus::Draft,
            'number' => 'ACM-INV-0003',
            'is_recurring' => false,
        ]);

        Livewire::test(TallStackRecurringInvoiceForm::class, ['company' => $this->company, 'invoice' => $plain])
            ->assertStatus(404);
    }

    public function test_saving_a_new_recurring_invoice_creates_a_template(): void
    {
        Livewire::test(TallStackRecurringInvoiceForm::class, ['company' => $this->company])
            ->set('client_id', (string) $this->client->id)
            ->set('recurring_frequency', 'quarterly')
            ->call('save');

        $template = Invoice::query()->where('company_id', $this->company->id)->where('is_recurring', true)->first();

        $this->assertNotNull($template);
        $this->assertSame('quarterly', $template->recurring_frequency);
        $this->assertSame(InvoiceStatus::Draft, $template->status);
    }
}
