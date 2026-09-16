<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Delivery Order detail — read-only line-item breakdown, matching the
 * Stitch "Delivery Order Detail & Logistics Verification" mockup's real
 * data (consignment lines with quantity delivered vs. the job line's
 * ordered quantity). The mockup also shows GPS coordinates, serial-number
 * capture and a digital signature panel — none of those exist in
 * App\Models\DeliveryOrder/DeliveryOrderItem's schema, so this page
 * deliberately does not invent them; it renders only fields the model
 * really has.
 *
 * A DeliveryOrder is immutable once recorded (written only through
 * App\Actions\Delivery\CompleteDelivery) — this page has no edit form,
 * only a link back to the owning job's workspace.
 */
#[Layout('components.tallstack.app')]
class TallStackDeliveryOrder extends Component
{
    public Company $company;

    public DeliveryOrder $deliveryOrder;

    public function mount(Company $company, DeliveryOrder $deliveryOrder): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);

        // Route binding for `deliveryOrder` happens before this mount()
        // body runs, before app(Tenancy::class)->set() above activates
        // BelongsToCompany's scope — cross-company access is checked
        // explicitly here, same reasoning as TallStackSalesOrder::mount().
        abort_unless($deliveryOrder->company_id === $company->id, 404);

        $this->deliveryOrder = $deliveryOrder->load(['salesOrder.client', 'items.salesOrderItem', 'createdBy']);
    }

    public function render(): View
    {
        $delivery = $this->deliveryOrder;
        $job = $delivery->salesOrder;

        // Cumulative quantity delivered per job line, across every
        // Delivery Order recorded for the job — one grouped query
        // covering every line on this delivery instead of one query per
        // line (a real N+1: this used to run its own
        // ->sum('quantity_delivered') query inside the per-item map()
        // below).
        $lineIds = $delivery->items->pluck('sales_order_item_id')->filter()->values();

        $deliveredTotals = $lineIds->isNotEmpty()
            ? DeliveryOrderItem::query()
                ->whereHas('deliveryOrder', fn ($q) => $q->where('sales_order_id', $job?->id))
                ->whereIn('sales_order_item_id', $lineIds)
                ->selectRaw('sales_order_item_id, sum(quantity_delivered) as total')
                ->groupBy('sales_order_item_id')
                ->pluck('total', 'sales_order_item_id')
            : collect();

        $items = $delivery->items->map(function (DeliveryOrderItem $item) use ($deliveredTotals) {
            $line = $item->salesOrderItem;

            $deliveredToDate = $line
                ? (float) ($deliveredTotals[$line->id] ?? 0)
                : (float) $item->quantity_delivered;

            return [
                'title' => $line?->title ?? $item->description ?? '—',
                'description' => $item->description ?: ($line?->description ?? '—'),
                'quantity_delivered' => (float) $item->quantity_delivered,
                // delivery_order_items has no unit column of its own — a
                // delivery line pulls its unit from the job line it's tied
                // to (sales_order_items.unit). An untied/ad-hoc delivery
                // line (sales_order_item_id null — the "Job line" picker on
                // the Record delivery modal is not required) has no unit to
                // show.
                'unit' => $line?->unit?->getAbbreviation() ?? '—',
                'ordered_quantity' => $line ? (float) $line->quantity : null,
                'delivered_to_date' => $line ? $deliveredToDate : null,
                'fully_delivered' => $line ? $deliveredToDate >= (float) $line->quantity : null,
            ];
        });

        return view('livewire.tallstack-delivery-order', [
            'job' => $job,
            'items' => $items,
        ])->layoutData([
            'company' => $this->company,
            'active' => 'delivery-orders',
            'title' => "Delivery Order {$delivery->number}",
        ]);
    }
}
