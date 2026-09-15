<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\RendersUnavailablePage;
use App\Models\DeliveryOrder;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The public "sign my delivery order" page `delivery_orders.portal_key`
 * resolves to — the same drawn-signature acceptance capability as
 * App\Livewire\Portal\ViewInvoice, extended to a Delivery Order per the
 * user's explicit request (electronic signing is otherwise deferred
 * launch scope, see CLAUDE.md).
 *
 * No auth: the unguessable `portal_key` (a UUID, minted in
 * App\Models\DeliveryOrder::booted()) *is* the credential — a Delivery
 * Order has no per-contact `Invitation` row the way an Invoice does (that
 * model is invoice-specific, not polymorphic), so this reuses the exact
 * same "unguessable key column on the record itself" shape instead of
 * generalizing Invitation into a polymorphic "signable" table for what is
 * only three call sites total.
 *
 * Routed (see routes/web.php) behind the same
 * App\Http\Middleware\ResolveCompanyFromDomain group as the invoice
 * portal, so a link can't be rendered under the *other* entity's domain
 * even by mistake — belt-and-suspenders on top of the key itself already
 * being unguessable.
 */
#[Layout('layouts.public')]
class SignDeliveryOrder extends Component
{
    use RendersUnavailablePage;

    public DeliveryOrder $deliveryOrder;

    public ?string $signerName = null;

    /**
     * Bound to TallStackUI's `<x-signature>` canvas (`wire:model`) — a
     * base64 image data URI, entangled live in
     * resources/views/livewire/portal/sign-delivery-order.blade.php. Null
     * until at least one stroke is drawn.
     */
    public ?string $capturedSignature = null;

    public bool $justSigned = false;

    public function mount(DeliveryOrder $deliveryOrder): void
    {
        $deliveryOrder->loadMissing('company', 'salesOrder.client', 'items.salesOrderItem');

        if (! app()->bound('currentCompany') || $deliveryOrder->company_id !== app('currentCompany')->id) {
            $this->abortUnavailable();
        }

        $this->deliveryOrder = $deliveryOrder;
    }

    public function sign(): void
    {
        $this->validate([
            'signerName' => ['required', 'string', 'max:255'],
            'capturedSignature' => ['required', 'string', 'starts_with:data:image/'],
        ], [
            'capturedSignature.required' => 'Please draw your signature before submitting.',
            'capturedSignature.starts_with' => 'Please draw your signature before submitting.',
        ]);

        $this->deliveryOrder->forceFill([
            'signed_by_name' => $this->signerName,
            'signature' => $this->capturedSignature,
            'signed_at' => now(),
        ])->save();

        $this->justSigned = true;
    }

    public function render(): View
    {
        return view('livewire.portal.sign-delivery-order');
    }
}
