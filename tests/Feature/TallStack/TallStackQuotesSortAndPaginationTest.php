<?php

namespace Tests\Feature\TallStack;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Livewire\TallStackQuotes;
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
 * filter must actually cap the page size.
 */
class TallStackQuotesSortAndPaginationTest extends TestCase
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

    private function quoteForClient(string $clientName): Invoice
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => $clientName]);

        return Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => InvoiceType::Quote,
            'status' => InvoiceStatus::Draft,
            'number' => 'ACME-QUO-'.uniqid(),
            'invoice_date' => now(),
        ]);
    }

    public function test_sorting_by_client_orders_by_the_joined_name_and_quantity_caps_the_page(): void
    {
        $this->quoteForClient('Zeta Client');
        $this->quoteForClient('Alpha Client');
        $this->quoteForClient('Mid Client');

        $component = Livewire::test(TallStackQuotes::class, ['company' => $this->company])
            ->set('sort', ['column' => 'client', 'direction' => 'asc'])
            ->assertHasNoErrors();

        $names = collect($component->viewData('quotes')->items())->pluck('client')->all();
        $this->assertSame(['Alpha Client', 'Mid Client', 'Zeta Client'], $names);

        $limited = Livewire::test(TallStackQuotes::class, ['company' => $this->company])
            ->set('quantity', 2)
            ->assertHasNoErrors();

        $this->assertCount(2, $limited->viewData('quotes')->items());
        $this->assertSame(3, $limited->viewData('quotes')->total());
    }
}
