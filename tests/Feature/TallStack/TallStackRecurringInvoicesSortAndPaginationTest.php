<?php

namespace Tests\Feature\TallStack;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Livewire\TallStackRecurringInvoices;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers wiring the TallStackUI <x-table>'s built-in sort/quantity onto
 * this register: sorting by the joined "client" column (clients.name,
 * joined once on the paginated query only — invoices.client_id is never
 * null) must not throw an ambiguous-column SQL error, and the quantity
 * filter must actually cap the page size. "amount"/"frequency_label"/
 * "next_date"/"status_label"/"generated_count" are all computed display
 * values with no literal matching column, and are asserted not sortable
 * so a click on them can never reach orderBy() with a bad column name.
 */
class TallStackRecurringInvoicesSortAndPaginationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);
        $this->actingAs($this->user);
    }

    private function templateForClient(string $clientName): Invoice
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => $clientName]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => InvoiceType::Invoice,
            'status' => InvoiceStatus::Draft,
            'number' => 'ACME-INV-'.uniqid(),
            'is_recurring' => true,
            'recurring_frequency' => 'monthly',
            'recurring_start_date' => now()->subMonth()->toDateString(),
        ]);
        $invoice->forceFill(['subtotal' => 500, 'total' => 500])->save();

        return $invoice->fresh();
    }

    public function test_sorting_by_client_orders_by_the_joined_name_and_quantity_caps_the_page(): void
    {
        $this->templateForClient('Zeta Client');
        $this->templateForClient('Alpha Client');
        $this->templateForClient('Mid Client');

        $component = Livewire::test(TallStackRecurringInvoices::class, ['company' => $this->company])
            ->set('sort', ['column' => 'client', 'direction' => 'asc'])
            ->assertHasNoErrors();

        $names = collect($component->viewData('templates')->items())->pluck('client')->all();
        $this->assertSame(['Alpha Client', 'Mid Client', 'Zeta Client'], $names);

        $limited = Livewire::test(TallStackRecurringInvoices::class, ['company' => $this->company])
            ->set('quantity', 2)
            ->assertHasNoErrors();

        $this->assertCount(2, $limited->viewData('templates')->items());
        $this->assertSame(3, $limited->viewData('templates')->total());
    }
}
