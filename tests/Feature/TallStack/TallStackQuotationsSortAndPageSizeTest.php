<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackQuotations;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the `<x-table>` `striped`/`quantity`/`:sort` wiring added to the
 * Quotations list — mirrors TallStackInvoicesSortAndPageSizeTest. The
 * `client` column isn't a real `quotations` column —
 * TallStackQuotations::render() joins `clients` and orders by
 * `clients.name` for that one case — so the main risk here is a
 * "no such column"/ambiguous-column SQL error on that path, not just a
 * wrong order.
 */
class TallStackQuotationsSortAndPageSizeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => 'owner']);

        return $user;
    }

    private function makeQuotation(string $clientName, string $number): Quotation
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => $clientName]);

        return Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => $number,
            'status' => 'draft',
        ]);
    }

    public function test_sorting_by_client_name_orders_rows_via_the_joined_clients_table_without_throwing(): void
    {
        $this->actingAs($this->owner());

        $this->makeQuotation('Zeta Client', 'ACM-QUO-0001');
        $this->makeQuotation('Alpha Client', 'ACM-QUO-0002');
        $this->makeQuotation('Mid Client', 'ACM-QUO-0003');

        $ascending = Livewire::test(TallStackQuotations::class, ['company' => $this->company])
            ->set('sort', ['column' => 'client', 'direction' => 'asc'])
            ->viewData('quotations');

        $this->assertSame(
            ['Alpha Client', 'Mid Client', 'Zeta Client'],
            collect($ascending->items())->pluck('client')->all(),
        );

        $descending = Livewire::test(TallStackQuotations::class, ['company' => $this->company])
            ->set('sort', ['column' => 'client', 'direction' => 'desc'])
            ->viewData('quotations');

        $this->assertSame(
            ['Zeta Client', 'Mid Client', 'Alpha Client'],
            collect($descending->items())->pluck('client')->all(),
        );
    }

    public function test_the_quantity_property_controls_how_many_rows_paginate_returns(): void
    {
        $this->actingAs($this->owner());

        for ($i = 1; $i <= 7; $i++) {
            $this->makeQuotation("Client {$i}", sprintf('ACM-QUO-%04d', $i));
        }

        $rows = Livewire::test(TallStackQuotations::class, ['company' => $this->company])
            ->set('quantity', 5)
            ->viewData('quotations');

        $this->assertCount(5, $rows->items());
        $this->assertSame(7, $rows->total());
    }
}
