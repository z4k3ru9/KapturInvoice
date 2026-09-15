<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackDeliveryOrder;
use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for a real N+1 found in this component's render():
 * for every delivery order line with a linked sales order item, it ran its
 * own DeliveryOrderItem::sum('quantity_delivered') query to compute
 * "delivered to date". render() now computes every line's cumulative total
 * with one grouped query instead of one query per line.
 */
class TallStackDeliveryOrderQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private SalesOrder $salesOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);
        $this->actingAs($this->user);

        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => 'ACM-QUO-'.uniqid(),
            'status' => 'draft',
        ]);

        $this->salesOrder = SalesOrder::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'number' => 'ACM-SO-'.uniqid(),
        ]);
    }

    /**
     * Builds one delivery order whose items are each linked to their own
     * sales order line, so every line needs its own "delivered to date"
     * computation.
     */
    private function makeDeliveryOrder(int $lineCount): DeliveryOrder
    {
        $deliveryOrder = DeliveryOrder::create([
            'company_id' => $this->company->id,
            'sales_order_id' => $this->salesOrder->id,
            'number' => 'ACM-DO-'.uniqid(),
            'delivery_date' => now(),
        ]);

        for ($i = 0; $i < $lineCount; $i++) {
            $line = SalesOrderItem::create([
                'sales_order_id' => $this->salesOrder->id,
                'title' => "Item {$i}",
                'quantity' => 10,
                'unit_cost' => 100,
                'line_total' => 1000,
            ]);

            DeliveryOrderItem::create([
                'delivery_order_id' => $deliveryOrder->id,
                'sales_order_item_id' => $line->id,
                'quantity_delivered' => 5,
            ]);
        }

        return $deliveryOrder;
    }

    private function queryCountForRender(DeliveryOrder $deliveryOrder): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        Livewire::test(TallStackDeliveryOrder::class, [
            'company' => $this->company,
            'deliveryOrder' => $deliveryOrder,
        ]);

        DB::flushQueryLog();

        return $count;
    }

    public function test_query_count_stays_flat_as_line_item_count_grows(): void
    {
        $small = $this->queryCountForRender($this->makeDeliveryOrder(2));
        $large = $this->queryCountForRender($this->makeDeliveryOrder(10));

        $this->assertSame($small, $large, 'A delivery order detail page must not issue more queries as its own line count grows.');
    }
}
