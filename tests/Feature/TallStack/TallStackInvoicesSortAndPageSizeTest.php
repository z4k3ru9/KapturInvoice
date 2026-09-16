<?php

namespace Tests\Feature\TallStack;

use App\Enums\InvoiceType;
use App\Livewire\TallStackInvoices;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the `<x-table>` `striped`/`quantity`/`:sort` wiring added to the
 * Invoices list (see vendor/tallstackui/tallstackui/.ai/components/table.md
 * for the attributes this exercises). The `client` column isn't a real
 * `invoices` column — TallStackInvoices::render() joins `clients` and
 * orders by `clients.name` for that one case — so the main risk here is a
 * "no such column"/ambiguous-column SQL error on that path, not just a
 * wrong order.
 */
class TallStackInvoicesSortAndPageSizeTest extends TestCase
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

    private function makeInvoice(string $clientName, string $number): Invoice
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => $clientName]);

        return Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => InvoiceType::Invoice,
            'status' => 'issued',
            'number' => $number,
        ]);
    }

    public function test_sorting_by_client_name_orders_rows_via_the_joined_clients_table_without_throwing(): void
    {
        $this->actingAs($this->owner());

        $this->makeInvoice('Zeta Client', 'ACM-INV-0001');
        $this->makeInvoice('Alpha Client', 'ACM-INV-0002');
        $this->makeInvoice('Mid Client', 'ACM-INV-0003');

        $ascending = Livewire::test(TallStackInvoices::class, ['company' => $this->company])
            ->set('sort', ['column' => 'client', 'direction' => 'asc'])
            ->viewData('invoices');

        $this->assertSame(
            ['Alpha Client', 'Mid Client', 'Zeta Client'],
            collect($ascending->items())->pluck('client')->all(),
        );

        $descending = Livewire::test(TallStackInvoices::class, ['company' => $this->company])
            ->set('sort', ['column' => 'client', 'direction' => 'desc'])
            ->viewData('invoices');

        $this->assertSame(
            ['Zeta Client', 'Mid Client', 'Alpha Client'],
            collect($descending->items())->pluck('client')->all(),
        );
    }

    public function test_the_quantity_property_controls_how_many_rows_paginate_returns(): void
    {
        $this->actingAs($this->owner());

        for ($i = 1; $i <= 7; $i++) {
            $this->makeInvoice("Client {$i}", sprintf('ACM-INV-%04d', $i));
        }

        $rows = Livewire::test(TallStackInvoices::class, ['company' => $this->company])
            ->set('quantity', 5)
            ->viewData('invoices');

        $this->assertCount(5, $rows->items());
        $this->assertSame(7, $rows->total());
    }
}
