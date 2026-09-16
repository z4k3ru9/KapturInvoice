<?php

namespace Tests\Feature\Delivery;

use App\Actions\Delivery\CompleteDelivery;
use App\Actions\Delivery\CompleteHandover;
use App\Actions\Sales\CloseJobOperationally;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Required tests from docs/rebuild/specs/05-procurement-and-delivery/Specs.md:
 * "Delivery-only operational closure", "Installation job blocked until
 * handover" — plus FINALIZED-DECISIONS.md §5's override-with-reason rule.
 */
class DeliveryAndHandoverTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role]);

        return $user;
    }

    private function makeJob(bool $requiresHandover, float $itemQuantity = 2.0): SalesOrder
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => 'ACM-QUO-'.random_int(100000, 999999),
        ]);

        $salesOrder = SalesOrder::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'number' => 'ACM-SO-'.random_int(100000, 999999),
            'approved_value' => 1000,
            'requires_handover' => $requiresHandover,
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'title' => 'Widget',
            'quantity' => $itemQuantity,
            'unit_cost' => 500,
            'line_total' => 500 * $itemQuantity,
        ]);

        return $salesOrder->fresh(['items']);
    }

    public function test_recording_a_delivery_order_with_items(): void
    {
        $job = $this->makeJob(requiresHandover: false);
        $staff = $this->userWithRole('staff');
        $item = $job->items->first();

        $deliveryOrder = app(CompleteDelivery::class)->complete($job, $staff, [
            ['sales_order_item_id' => $item->id, 'description' => $item->title, 'quantity_delivered' => 2.0],
        ]);

        $this->assertNotNull($deliveryOrder->number);
        $this->assertSame(1, $deliveryOrder->items->count());
        $this->assertSame('2.0000', $deliveryOrder->items->first()->quantity_delivered);
        $this->assertSame($staff->id, $deliveryOrder->created_by_user_id);
    }

    public function test_a_goods_only_job_auto_closes_operationally_after_one_partial_delivery(): void
    {
        $job = $this->makeJob(requiresHandover: false, itemQuantity: 5.0);
        $staff = $this->userWithRole('staff');
        $item = $job->items->first();

        // Only a partial quantity is delivered — not fully delivered, but a
        // goods-only job's closure condition only needs at least one
        // recorded Delivery Order, so CompleteDelivery's own auto-close
        // hook (status-transition automation) fires immediately here.
        app(CompleteDelivery::class)->complete($job, $staff, [
            ['sales_order_item_id' => $item->id, 'quantity_delivered' => 1.0],
        ]);

        $this->assertFalse($job->fresh()->isFullyDelivered());
        $this->assertNotNull($job->fresh()->operational_closed_at);

        // Already auto-closed — a manual close attempt now correctly denies.
        $this->expectException(RuntimeException::class);
        app(CloseJobOperationally::class)->close($job->fresh());
    }

    public function test_an_installation_job_is_blocked_from_operational_closure_until_handover_exists(): void
    {
        $job = $this->makeJob(requiresHandover: true);
        $staff = $this->userWithRole('staff');
        $item = $job->items->first();

        app(CompleteDelivery::class)->complete($job, $staff, [
            ['sales_order_item_id' => $item->id, 'quantity_delivered' => 2.0],
        ]);

        $this->expectException(RuntimeException::class);

        app(CloseJobOperationally::class)->close($job->fresh());
    }

    public function test_handover_without_full_delivery_and_without_override_throws(): void
    {
        $job = $this->makeJob(requiresHandover: true, itemQuantity: 5.0);
        $staff = $this->userWithRole('staff');
        $item = $job->items->first();

        app(CompleteDelivery::class)->complete($job, $staff, [
            ['sales_order_item_id' => $item->id, 'quantity_delivered' => 1.0],
        ]);

        $this->expectException(RuntimeException::class);

        app(CompleteHandover::class)->complete($job->fresh(), $staff);
    }

    public function test_handover_override_by_an_owner_with_a_reason_succeeds_despite_incomplete_delivery(): void
    {
        $job = $this->makeJob(requiresHandover: true, itemQuantity: 5.0);
        $staff = $this->userWithRole('staff');
        $owner = $this->userWithRole('owner');
        $item = $job->items->first();

        app(CompleteDelivery::class)->complete($job, $staff, [
            ['sales_order_item_id' => $item->id, 'quantity_delivered' => 1.0],
        ]);

        $handover = app(CompleteHandover::class)->complete(
            $job->fresh(),
            $owner,
            notes: 'Service-only handover',
            override: true,
            overrideReason: 'Valid service-only exceptional work',
        );

        $this->assertTrue($handover->is_override);
        $this->assertSame('Valid service-only exceptional work', $handover->override_reason);
        $this->assertNotNull($handover->number);

        // CompleteHandover's own auto-close hook fires immediately once the
        // handover exists (a job requiring handover is now eligible) — no
        // manual "Close operationally" click needed.
        $this->assertNotNull($job->fresh()->operational_closed_at);
    }

    public function test_handover_override_is_denied_for_a_staff_actor_even_with_a_reason(): void
    {
        $job = $this->makeJob(requiresHandover: true, itemQuantity: 5.0);
        $staff = $this->userWithRole('staff');
        $item = $job->items->first();

        app(CompleteDelivery::class)->complete($job, $staff, [
            ['sales_order_item_id' => $item->id, 'quantity_delivered' => 1.0],
        ]);

        $this->expectException(RuntimeException::class);

        app(CompleteHandover::class)->complete(
            $job->fresh(),
            $staff,
            override: true,
            overrideReason: 'Trying to bypass as staff',
        );
    }

    public function test_a_held_job_is_not_auto_closed_even_when_its_condition_is_met(): void
    {
        $job = $this->makeJob(requiresHandover: false);
        $staff = $this->userWithRole('staff');
        $item = $job->items->first();

        $job->forceFill(['held_at' => now(), 'held_reason' => 'Pending revision approval'])->save();

        app(CompleteDelivery::class)->complete($job, $staff, [
            ['sales_order_item_id' => $item->id, 'quantity_delivered' => 2.0],
        ]);

        $this->assertNull($job->fresh()->operational_closed_at);
    }

    public function test_operational_closure_is_denied_the_second_time_it_is_attempted(): void
    {
        $job = $this->makeJob(requiresHandover: false);
        $staff = $this->userWithRole('staff');
        $item = $job->items->first();

        // CompleteDelivery's own auto-close hook already closes the job
        // here (goods-only, at least one Delivery Order recorded) — no
        // manual first call needed to reach the "already closed" state.
        app(CompleteDelivery::class)->complete($job, $staff, [
            ['sales_order_item_id' => $item->id, 'quantity_delivered' => 2.0],
        ]);

        $this->assertNotNull($job->fresh()->operational_closed_at);

        $this->expectException(RuntimeException::class);

        app(CloseJobOperationally::class)->close($job->fresh());
    }
}
