<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\SalesOrders\Pages\ListSalesOrders;
use App\Filament\Resources\SalesOrders\Pages\ViewSalesOrder;
use App\Filament\Resources\SalesOrders\RelationManagers\DeliveryOrdersRelationManager;
use App\Filament\Resources\SalesOrders\RelationManagers\HandoverReportsRelationManager;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Exercises the Phase 05 (docs/rebuild/specs/05-procurement-and-delivery)
 * delivery/handover/closure wiring added to SalesOrderResource — the
 * underlying Action classes (App\Actions\Delivery\*, App\Actions\Sales\
 * CloseJob*) are unit tested elsewhere; this file proves the Livewire
 * wiring itself actually calls them, mirroring
 * PaymentReceivablesActionsTest's style.
 */
class DeliveryHandoverClosureActionsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
        app(Tenancy::class)->set($this->company);
    }

    private function jobWithOneItem(bool $requiresHandover = false): SalesOrder
    {
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'ACME-QUO-'.uniqid(),
            'status' => 'draft',
        ]);

        $salesOrder = SalesOrder::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'quotation_id' => $quotation->id,
            'number' => 'ACME-SO-'.uniqid(),
            'status' => 'draft',
            'approved_value' => 1000,
            'requires_handover' => $requiresHandover,
        ]);

        $salesOrder->items()->create(['title' => 'Widget', 'quantity' => 2, 'unit_cost' => 500]);

        return $salesOrder;
    }

    public function test_the_record_delivery_relation_manager_action_records_a_delivery_order(): void
    {
        $salesOrder = $this->jobWithOneItem();
        $item = $salesOrder->items->first();

        Livewire::test(DeliveryOrdersRelationManager::class, ['ownerRecord' => $salesOrder, 'pageClass' => ViewSalesOrder::class])
            ->callTableAction('recordDelivery', data: [
                'notes' => 'Partial delivery',
                'items' => [
                    ['sales_order_item_id' => $item->id, 'description' => 'Widget', 'quantity_delivered' => 2],
                ],
            ]);

        $this->assertSame(1, $salesOrder->deliveryOrders()->count());
        $this->assertNotNull($salesOrder->deliveryOrders()->first()->number);
    }

    public function test_the_close_operationally_table_action_closes_a_delivery_only_job_after_delivery(): void
    {
        $salesOrder = $this->jobWithOneItem(requiresHandover: false);
        $item = $salesOrder->items->first();

        Livewire::test(DeliveryOrdersRelationManager::class, ['ownerRecord' => $salesOrder, 'pageClass' => ViewSalesOrder::class])
            ->callTableAction('recordDelivery', data: [
                'items' => [
                    ['sales_order_item_id' => $item->id, 'description' => 'Widget', 'quantity_delivered' => 2],
                ],
            ]);

        Livewire::test(ListSalesOrders::class)
            ->callTableAction('closeOperationally', $salesOrder);

        $this->assertNotNull($salesOrder->fresh()->operational_closed_at);
    }

    public function test_the_close_operationally_table_action_blocks_an_installation_job_without_handover(): void
    {
        $salesOrder = $this->jobWithOneItem(requiresHandover: true);

        Livewire::test(ListSalesOrders::class)
            ->callTableAction('closeOperationally', $salesOrder);

        $this->assertNull($salesOrder->fresh()->operational_closed_at);
    }

    public function test_the_record_handover_relation_manager_action_records_a_handover_after_full_delivery(): void
    {
        $salesOrder = $this->jobWithOneItem(requiresHandover: true);
        $item = $salesOrder->items->first();

        Livewire::test(DeliveryOrdersRelationManager::class, ['ownerRecord' => $salesOrder, 'pageClass' => ViewSalesOrder::class])
            ->callTableAction('recordDelivery', data: [
                'items' => [
                    ['sales_order_item_id' => $item->id, 'description' => 'Widget', 'quantity_delivered' => 2],
                ],
            ]);

        Livewire::test(HandoverReportsRelationManager::class, ['ownerRecord' => $salesOrder, 'pageClass' => ViewSalesOrder::class])
            ->callTableAction('recordHandover', data: [
                'notes' => 'Handed over to client',
                'override' => false,
            ]);

        $this->assertSame(1, $salesOrder->handoverReports()->count());

        Livewire::test(ListSalesOrders::class)
            ->callTableAction('closeOperationally', $salesOrder);

        $this->assertNotNull($salesOrder->fresh()->operational_closed_at);
    }

    public function test_the_close_financially_table_action_closes_a_job_with_no_outstanding_balance(): void
    {
        $salesOrder = $this->jobWithOneItem();

        Livewire::test(ListSalesOrders::class)
            ->callTableAction('closeFinancially', $salesOrder);

        $this->assertNotNull($salesOrder->fresh()->financial_closed_at);
    }

    private function outstandingInvoiceFor(SalesOrder $salesOrder): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'sales_order_id' => $salesOrder->id,
            'type' => 'invoice',
            'status' => 'issued',
            'number' => 'ACME-INV-'.uniqid(),
        ]);
        $invoice->forceFill(['total' => 1000, 'balance' => 1000, 'amount_paid' => 0])->save();

        return $invoice;
    }

    public function test_the_close_financially_table_action_is_blocked_by_an_outstanding_invoice_without_override(): void
    {
        $salesOrder = $this->jobWithOneItem();
        $this->outstandingInvoiceFor($salesOrder);

        Livewire::test(ListSalesOrders::class)
            ->callTableAction('closeFinancially', $salesOrder);

        $this->assertNull($salesOrder->fresh()->financial_closed_at);
    }

    public function test_the_close_financially_table_action_succeeds_with_an_owner_override(): void
    {
        $salesOrder = $this->jobWithOneItem();
        $this->outstandingInvoiceFor($salesOrder);

        Livewire::test(ListSalesOrders::class)
            ->callTableAction('closeFinancially', $salesOrder, data: [
                'override' => true,
                'override_reason' => 'Client dispute pending legal resolution',
                'outstanding_balance_summary' => 'IDR 1,000 outstanding, awaiting resolution',
            ]);

        $this->assertNotNull($salesOrder->fresh()->financial_closed_at);
    }
}
