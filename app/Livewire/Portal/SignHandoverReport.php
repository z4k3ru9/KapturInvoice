<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\RendersUnavailablePage;
use App\Models\HandoverReport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The public "sign my handover report" page
 * `handover_reports.portal_key` resolves to — same drawn-signature
 * acceptance capability as App\Livewire\Portal\ViewInvoice/
 * App\Livewire\Portal\SignDeliveryOrder; see SignDeliveryOrder's docblock
 * for the credential-mechanism reasoning, identical here.
 */
#[Layout('layouts.public')]
class SignHandoverReport extends Component
{
    use RendersUnavailablePage;

    public HandoverReport $handoverReport;

    public ?string $signerName = null;

    public ?string $capturedSignature = null;

    public bool $justSigned = false;

    public function mount(HandoverReport $handoverReport): void
    {
        $handoverReport->loadMissing('company', 'salesOrder.client');

        if (! app()->bound('currentCompany') || $handoverReport->company_id !== app('currentCompany')->id) {
            $this->abortUnavailable();
        }

        $this->handoverReport = $handoverReport;
    }

    public function sign(): void
    {
        $this->validate([
            'signerName' => ['required', 'string', 'max:255'],
            // See App\Livewire\Portal\SignDeliveryOrder::sign() for why this
            // unauthenticated endpoint needs an explicit length cap on top
            // of the `longtext` column's own (deliberately unbounded) size.
            'capturedSignature' => ['required', 'string', 'max:2000000', 'starts_with:data:image/'],
        ], [
            'capturedSignature.required' => 'Please draw your signature before submitting.',
            'capturedSignature.starts_with' => 'Please draw your signature before submitting.',
        ]);

        $this->handoverReport->forceFill([
            'signed_by_name' => $this->signerName,
            'signature' => $this->capturedSignature,
            'signed_at' => now(),
        ])->save();

        $this->justSigned = true;
    }

    public function render(): View
    {
        return view('livewire.portal.sign-handover-report');
    }
}
