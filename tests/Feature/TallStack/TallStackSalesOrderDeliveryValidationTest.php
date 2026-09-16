<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackSalesOrder;
use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryOrder;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Quantity delivered must always be a whole number going forward (forward-
 * looking validation only — `delivery_order_items.quantity_delivered` stays
 * decimal(15,4), never narrowed, since historical rows may already be
 * fractional). Covers the Livewire-level validation on the Job page's
 * "Record delivery" modal (App\Livewire\TallStackSalesOrder::recordDelivery);
 * App\Actions\Delivery\CompleteDelivery's own equivalent guard is covered
 * directly in tests/Feature/Delivery/DeliveryAndHandoverTest.php.
 */
class TallStackSalesOrderDeliveryValidationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private SalesOrder $job;

    private SalesOrderItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => 'ACM-QUO-0001',
            'status' => 'draft',
        ]);
        $this->job = SalesOrder::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'number' => 'ACM-SO-0001',
        ])->fresh(); // reload so the DB-default `status` column is actually cast (unset until refetched — same reasoning as DeliveryAndHandoverTest::makeJob()).
        $this->item = SalesOrderItem::create([
            'sales_order_id' => $this->job->id,
            'title' => 'Widget',
            'quantity' => 10,
            'unit_cost' => 100,
            'line_total' => 1000,
        ]);
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => 'staff']);

        return $user;
    }

    public function test_a_fractional_delivered_quantity_is_rejected_with_a_validation_error(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(TallStackSalesOrder::class, ['company' => $this->company, 'salesOrder' => $this->job])
            ->call('openDeliveryModal')
            ->set('delivery_items.0.sales_order_item_id', (string) $this->item->id)
            ->set('delivery_items.0.quantity_delivered', 1.5)
            ->call('recordDelivery')
            ->assertHasErrors(['delivery_items.0.quantity_delivered']);

        $this->assertSame(0, DeliveryOrder::query()->count());
    }

    public function test_a_zero_delivered_quantity_is_rejected_with_a_validation_error(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(TallStackSalesOrder::class, ['company' => $this->company, 'salesOrder' => $this->job])
            ->call('openDeliveryModal')
            ->set('delivery_items.0.sales_order_item_id', (string) $this->item->id)
            ->set('delivery_items.0.quantity_delivered', 0)
            ->call('recordDelivery')
            ->assertHasErrors(['delivery_items.0.quantity_delivered']);

        $this->assertSame(0, DeliveryOrder::query()->count());
    }

    public function test_a_whole_number_delivered_quantity_is_recorded(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(TallStackSalesOrder::class, ['company' => $this->company, 'salesOrder' => $this->job])
            ->call('openDeliveryModal')
            ->set('delivery_items.0.sales_order_item_id', (string) $this->item->id)
            ->set('delivery_items.0.quantity_delivered', 4)
            ->call('recordDelivery')
            ->assertHasNoErrors();

        $this->assertSame(1, DeliveryOrder::query()->count());
        $this->assertSame('4.0000', DeliveryOrder::first()->items->first()->quantity_delivered);
    }
}
