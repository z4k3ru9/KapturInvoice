<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use Filament\Facades\Filament;
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

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company, isQuiet: true);

        // Route binding for `deliveryOrder` happens before this mount()
        // body runs, before Filament::setTenant() above activates
        // BelongsToCompany's scope — cross-company access is checked
        // explicitly here, same reasoning as TallStackSalesOrder::mount().
        abort_unless($deliveryOrder->company_id === $company->id, 404);

        $this->deliveryOrder = $deliveryOrder->load(['salesOrder.client', 'items.salesOrderItem', 'createdBy']);
    }

    public function render(): View
    {
        $delivery = $this->deliveryOrder;
        $job = $delivery->salesOrder;

        $items = $delivery->items->map(function (DeliveryOrderItem $item) use ($job) {
            $line = $item->salesOrderItem;

            // Cumulative quantity delivered against this same job line
            // across every Delivery Order recorded for the job — derived
            // purely from existing DeliveryOrderItem rows, not a new
            // stored figure.
            $deliveredToDate = $line
                ? (float) DeliveryOrderItem::query()
                    ->whereHas('deliveryOrder', fn ($q) => $q->where('sales_order_id', $job?->id))
                    ->where('sales_order_item_id', $line->id)
                    ->sum('quantity_delivered')
                : (float) $item->quantity_delivered;

            return [
                'title' => $line?->title ?? $item->description ?? '—',
                'description' => $item->description ?: ($line?->description ?? '—'),
                'quantity_delivered' => (float) $item->quantity_delivered,
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
