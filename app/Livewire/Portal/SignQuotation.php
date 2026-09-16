<?php

namespace App\Livewire\Portal;

use App\Actions\Sales\AcceptQuotation;
use App\Enums\QuotationStatus;
use App\Livewire\Portal\Concerns\RendersUnavailablePage;
use App\Models\Quotation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * The public "review and accept my quotation" page
 * `quotations.portal_key` resolves to — the same drawn-signature
 * acceptance capability as App\Livewire\Portal\ViewInvoice, extended to
 * quotation approval. See App\Livewire\Portal\SignDeliveryOrder's
 * docblock for the credential-mechanism reasoning, identical here.
 *
 * Acceptance itself still goes through the existing
 * App\Actions\Sales\AcceptQuotation action — this component never sets
 * `status`/`accepted_at`/`customer_po_*` directly, only the signature
 * fields alongside what that action already decided (system-generated
 * Customer Order Confirmation vs. a supplied customer PO number/date).
 * Only a `Sent` quotation can be accepted here — the same rule
 * `QuotationStatus::canTransitionTo()` already enforces; an out-of-state
 * quotation renders read-only instead of a sign form.
 */
#[Layout('layouts.public')]
class SignQuotation extends Component
{
    use RendersUnavailablePage;

    public Quotation $quotation;

    public ?string $signerName = null;

    public ?string $customerPoNumber = null;

    public ?string $customerPoDate = null;

    /**
     * Bound to TallStackUI's `<x-signature>` canvas (`wire:model`) — a
     * base64 image data URI, entangled live in
     * resources/views/livewire/portal/sign-quotation.blade.php. Null
     * until at least one stroke is drawn.
     */
    public ?string $capturedSignature = null;

    public bool $justSigned = false;

    public function mount(Quotation $quotation): void
    {
        $quotation->loadMissing('company', 'client', 'items');

        if (! app()->bound('currentCompany') || $quotation->company_id !== app('currentCompany')->id) {
            $this->abortUnavailable();
        }

        // Informational only — mirrors App\Livewire\Portal\ViewInvoice's
        // Invitation::viewed_at write, but a Quotation has no wrapping
        // Invitation record, so it lives directly on the quotation. See
        // this column's own migration docblock for why this deliberately
        // does NOT also flip `status` to a new Viewed case.
        if (! $quotation->viewed_at) {
            $quotation->forceFill(['viewed_at' => now()])->saveQuietly();
        }

        $this->quotation = $quotation;
    }

    public function accept(): void
    {
        if (! $this->quotation->status->canTransitionTo(QuotationStatus::Accepted)) {
            $this->addError('capturedSignature', 'This quotation is no longer available for acceptance.');

            return;
        }

        $this->validate([
            'signerName' => ['required', 'string', 'max:255'],
            'capturedSignature' => ['required', 'string', 'starts_with:data:image/'],
        ], [
            'capturedSignature.required' => 'Please draw your signature before submitting.',
            'capturedSignature.starts_with' => 'Please draw your signature before submitting.',
        ]);

        try {
            app(AcceptQuotation::class)->accept(
                $this->quotation,
                filled($this->customerPoNumber) ? $this->customerPoNumber : null,
                filled($this->customerPoDate) ? Carbon::parse($this->customerPoDate) : null,
            );
        } catch (RuntimeException $e) {
            $this->addError('capturedSignature', $e->getMessage());

            return;
        }

        $this->quotation->forceFill([
            'signed_by_name' => $this->signerName,
            'signature' => $this->capturedSignature,
            'signed_at' => now(),
        ])->save();

        $this->justSigned = true;
    }

    public function render(): View
    {
        return view('livewire.portal.sign-quotation');
    }
}
