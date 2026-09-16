<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackSalesOrders;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the `<x-table>` `striped`/`quantity`/`:sort` wiring added to the
 * Jobs (SalesOrder) list — mirrors TallStackInvoicesSortAndPageSizeTest.
 * The `client` column isn't a real `sales_orders` column —
 * TallStackSalesOrders::render() joins `clients` and orders by
 * `clients.name` for that one case — so the main risk here is a
 * "no such column"/ambiguous-column SQL error on that path, not just a
 * wrong order.
 */
class TallStackSalesOrdersSortAndPageSizeTest extends TestCase
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

    private function makeJob(string $clientName, string $number): SalesOrder
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => $clientName]);
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => 'Q-'.$number,
            'status' => 'draft',
        ]);

        return SalesOrder::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'number' => $number,
        ]);
    }

    public function test_sorting_by_client_name_orders_rows_via_the_joined_clients_table_without_throwing(): void
    {
        $this->actingAs($this->owner());

        $this->makeJob('Zeta Client', 'ACM-SO-0001');
        $this->makeJob('Alpha Client', 'ACM-SO-0002');
        $this->makeJob('Mid Client', 'ACM-SO-0003');

        $ascending = Livewire::test(TallStackSalesOrders::class, ['company' => $this->company])
            ->set('sort', ['column' => 'client', 'direction' => 'asc'])
            ->viewData('jobs');

        $this->assertSame(
            ['Alpha Client', 'Mid Client', 'Zeta Client'],
            collect($ascending->items())->pluck('client')->all(),
        );

        $descending = Livewire::test(TallStackSalesOrders::class, ['company' => $this->company])
            ->set('sort', ['column' => 'client', 'direction' => 'desc'])
            ->viewData('jobs');

        $this->assertSame(
            ['Zeta Client', 'Mid Client', 'Alpha Client'],
            collect($descending->items())->pluck('client')->all(),
        );
    }

    public function test_the_quantity_property_controls_how_many_rows_paginate_returns(): void
    {
        $this->actingAs($this->owner());

        for ($i = 1; $i <= 7; $i++) {
            $this->makeJob("Client {$i}", sprintf('ACM-SO-%04d', $i));
        }

        $rows = Livewire::test(TallStackSalesOrders::class, ['company' => $this->company])
            ->set('quantity', 5)
            ->viewData('jobs');

        $this->assertCount(5, $rows->items());
        $this->assertSame(7, $rows->total());
    }
}
