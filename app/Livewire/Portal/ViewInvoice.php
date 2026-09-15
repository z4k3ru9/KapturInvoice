<?php

namespace App\Livewire\Portal;

use App\Enums\InvoiceStatus;
use App\Livewire\Portal\Concerns\RendersUnavailablePage;
use App\Models\Invitation;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The public "view my invoice/quote" page `invitations.key` resolves to —
 * closes the gap flagged in docs/filament-admin-layout-design.md §2.2/§3.5.
 * No auth: the unguessable `key` (a UUID, minted in Invitation::booted())
 * *is* the credential, same as the legacy `invitation_key` it replaces.
 *
 * Routed (see routes/web.php) behind the same
 * App\Http\Middleware\ResolveCompanyFromDomain group as the public
 * homepage, so `{{ $company }}` branding is available in the layout and,
 * more importantly, so a portal link can't be rendered under the *other*
 * entity's domain even by mistake — belt-and-suspenders on top of the key
 * itself already being unguessable.
 */
#[Layout('layouts.public')]
class ViewInvoice extends Component
{
    use RendersUnavailablePage;

    public Invitation $invitation;

    /**
     * Bound to TallStackUI's `<x-signature>` canvas (`wire:model`) — a
     * base64 image data URI (`data:image/png;base64,...`) synced from the
     * drawn strokes, entangled live in resources/views/livewire/portal/
     * view-invoice.blade.php. Null until at least one stroke is drawn.
     */
    public ?string $capturedSignature = null;

    public bool $justSigned = false;

    public function mount(Invitation $invitation): void
    {
        $invitation->loadMissing(
            'invoice.client',
            'invoice.company.settings',
            'invoice.items.taxes',
            'invoice.payments',
            'invoice.credits',
            'invoice.documents',
            'contact',
        );

        if (! app()->bound('currentCompany') || $invitation->invoice->company_id !== app('currentCompany')->id) {
            $this->abortUnavailable();
        }

        if (! $invitation->viewed_at) {
            $invitation->forceFill(['viewed_at' => now()])->save();
        }

        if ($invitation->invoice->status === InvoiceStatus::Sent) {
            $invitation->invoice->forceFill(['status' => InvoiceStatus::Viewed])->saveQuietly();
        }

        $this->invitation = $invitation;
    }

    public function sign(): void
    {
        $this->validate([
            'capturedSignature' => ['required', 'string', 'starts_with:data:image/'],
        ], [
            'capturedSignature.required' => 'Please draw your signature before submitting.',
            'capturedSignature.starts_with' => 'Please draw your signature before submitting.',
        ]);

        $this->invitation->forceFill([
            'signature' => $this->capturedSignature,
            'signed_at' => now(),
        ])->save();

        $this->justSigned = true;
    }

    public function render(): View
    {
        return view('livewire.portal.view-invoice');
    }
}
