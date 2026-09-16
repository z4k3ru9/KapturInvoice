<?php

namespace App\Actions\Delivery;

use App\Actions\Sales\CloseJobOperationally;
use App\Enums\CompanyRole;
use App\Models\DeliveryOrder;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentNumberGenerator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "Delivery Order applies to all applicable delivery events, including
 * delivery-only jobs... A job may have multiple partial Delivery Orders."
 * — docs/rebuild/specs/05-procurement-and-delivery/Specs.md,
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §5. "Staff and higher can
 * record/approve delivery and handover" — CompanyRole::deliveryAndHandoverRoles().
 * Recording multiple partial Delivery Orders against the same job is the
 * normal case, not an error.
 */
class CompleteDelivery
{
    public function __construct(
        private DocumentNumberGenerator $numberGenerator,
        private AuditLogger $auditLogger,
        private CloseJobOperationally $closeJobOperationally,
    ) {}

    /**
     * @param  array<int, array{sales_order_item_id?: int|null, description?: ?string, quantity_delivered: float}>  $items
     */
    public function complete(SalesOrder $salesOrder, User $actor, array $items, ?string $notes = null): DeliveryOrder
    {
        if (! $actor->hasCompanyRole($salesOrder->company, ...CompanyRole::deliveryAndHandoverRoles())) {
            throw new RuntimeException('Only Staff and higher may record a delivery.');
        }

        $number = $this->numberGenerator->next($salesOrder->company, 'delivery_order');

        $deliveryOrder = DB::transaction(function () use ($salesOrder, $actor, $items, $notes, $number) {
            $deliveryOrder = DeliveryOrder::create([
                'company_id' => $salesOrder->company_id,
                'sales_order_id' => $salesOrder->id,
                'number' => $number,
                'delivery_date' => now()->toDateString(),
                'notes' => $notes,
                'created_by_user_id' => $actor->id,
            ]);

            foreach (array_values($items) as $index => $entry) {
                $deliveryOrder->items()->create([
                    'sales_order_item_id' => $entry['sales_order_item_id'] ?? null,
                    'description' => $entry['description'] ?? null,
                    'quantity_delivered' => $entry['quantity_delivered'],
                    'sort_order' => $index,
                ]);
            }

            return $deliveryOrder;
        });

        $this->auditLogger->record(
            $salesOrder->company,
            'delivery_order.recorded',
            $deliveryOrder,
            [],
            ['number' => $number],
        );

        $this->autoCloseIfEligible($salesOrder);

        return $deliveryOrder->fresh(['items']);
    }

    /**
     * Status-transition automation: a goods-only job's operational
     * closure condition (at least one recorded Delivery Order) is fully
     * computed already — App\Actions\Sales\CloseJobOperationally's own
     * check, not a new one. Auto-close the moment it's satisfied rather
     * than waiting for a manual "Close operationally" click.
     * RuntimeException here just means "not yet met" (a job requiring
     * handover, or one still missing a requirement) — not a real error,
     * so it's swallowed. A held job (App\Models\Concerns\Holdable) is
     * left alone; placing a hold is the intended way to pause this
     * specific automation on one record.
     */
    private function autoCloseIfEligible(SalesOrder $salesOrder): void
    {
        $fresh = $salesOrder->fresh();

        if ($fresh->isHeld() || $fresh->operational_closed_at !== null) {
            return;
        }

        try {
            $this->closeJobOperationally->close($fresh);
        } catch (RuntimeException) {
            // Not yet eligible — nothing to do.
        }
    }
}
