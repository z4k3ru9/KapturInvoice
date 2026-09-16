<?php

namespace App\Livewire;

use App\Actions\Sales\AcceptQuotation;
use App\Actions\Sales\CreateSalesOrderFromQuotation;
use App\Actions\Sales\ForceDeleteQuotation;
use App\Actions\Sales\TransitionQuotationStatus;
use App\Enums\QuotationStatus;
use App\Models\Company;
use App\Models\Quotation;
use App\Services\Sales\QuotationMailer;
use App\Support\Dashboard\Money;
use App\Support\TallStack\StatusColor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the Quotations register — see App\Livewire\TallStackDashboard's docblock
 * for the established pattern this follows. Reuses App\Models\Quotation,
 * App\Enums\QuotationStatus, and every App\Actions\Sales\* action the
 * equivalent pre-TallStackUI Filament quotations table already used for
 * status transitions — this is a presentation-layer swap only, never a
 * reimplementation of the state machine.
 */
#[Layout('components.tallstack.app')]
class TallStackQuotations extends Component
{
    use Interactions, WithPagination;

    public Company $company;

    public ?string $status = null;

    public string $search = '';

    public array $sort = ['column' => 'quotation_date', 'direction' => 'desc'];

    /** Bound to the Accept modal — the quotation id currently being accepted. */
    public ?int $acceptingId = null;

    public bool $showAcceptModal = false;

    public ?string $customerPoNumber = null;

    public ?string $customerPoDate = null;

    /**
     * The row currently shown in the "View signature" modal — a client
     * e-signature captured on the public portal link
     * (App\Livewire\Portal\SignQuotation).
     */
    public ?int $viewingSignatureId = null;

    public bool $showSignatureModal = false;

    public function viewSignature(int $id): void
    {
        $this->viewingSignatureId = $id;
        $this->showSignatureModal = true;
    }

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        // Same reasoning as TallStackDashboard::mount() — Resource::getUrl()
        // (used indirectly nowhere on this page today, kept for parity with
        // the rest of the TALL-stack pages so a future addition doesn't
        // silently need this again) needs a resolved panel + tenant even
        // outside a real panel request.
        app(Tenancy::class)->set($company);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function filterStatus(?string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function approve(int $id): void
    {
        $this->applyTransition($id, QuotationStatus::Approved);
    }

    /**
     * Same reasoning as TallStackQuotationForm::send() — the status
     * transition stays a separate, already-tested concern, and this only
     * adds the email dispatch alongside it.
     */
    public function send(int $id): void
    {
        $quotation = $this->findScoped($id);

        if (! $quotation) {
            return;
        }

        $this->authorize('update', $quotation);

        try {
            app(TransitionQuotationStatus::class)->transition($quotation, QuotationStatus::Sent);
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not update quotation', $e->getMessage())->send();

            return;
        }

        try {
            app(QuotationMailer::class)->sendQuotation($quotation);
            $this->toast()->success('Quotation sent.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Quotation marked as sent, but the email could not be sent', $e->getMessage())->send();
        }
    }

    public function reject(int $id): void
    {
        $this->applyTransition($id, QuotationStatus::Rejected);
    }

    public function markExpired(int $id): void
    {
        $this->applyTransition($id, QuotationStatus::Expired);
    }

    public function cancel(int $id): void
    {
        $this->applyTransition($id, QuotationStatus::Cancelled);
    }

    public function openAcceptModal(int $id): void
    {
        $this->acceptingId = $id;
        $this->customerPoNumber = null;
        $this->customerPoDate = null;
        $this->showAcceptModal = true;
    }

    public function accept(): void
    {
        $quotation = $this->findScoped($this->acceptingId);

        if (! $quotation) {
            return;
        }

        $this->authorize('update', $quotation);

        try {
            app(AcceptQuotation::class)->accept(
                $quotation,
                filled($this->customerPoNumber) ? $this->customerPoNumber : null,
                filled($this->customerPoDate) ? Carbon::parse($this->customerPoDate) : null,
            );

            $this->showAcceptModal = false;
            $this->toast()->success('Quotation accepted.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not accept quotation', $e->getMessage())->send();
        }
    }

    public function createJob(int $id): void
    {
        $quotation = $this->findScoped($id);

        if (! $quotation) {
            return;
        }

        $this->authorize('update', $quotation);

        try {
            $salesOrder = app(CreateSalesOrderFromQuotation::class)->create($quotation);

            $this->toast()->success('Job created', "Created job #{$salesOrder->number}.")->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not create job', $e->getMessage())->send();
        }
    }

    public function forceDelete(int $id): void
    {
        $quotation = $this->findScoped($id);

        if (! $quotation) {
            return;
        }

        try {
            app(ForceDeleteQuotation::class)->forceDelete($quotation, auth()->user());
            $this->toast()->success('Quotation permanently deleted.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not delete quotation', $e->getMessage())->send();
        }
    }

    private function applyTransition(int $id, QuotationStatus $to): void
    {
        $quotation = $this->findScoped($id);

        if (! $quotation) {
            return;
        }

        $this->authorize('update', $quotation);

        try {
            app(TransitionQuotationStatus::class)->transition($quotation, $to);
            $this->toast()->success('Quotation updated.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not update quotation', $e->getMessage())->send();
        }
    }

    /** Never trust a bare `Quotation::find()` here — always re-check company ownership, the same explicit guard the PDF controllers use since this route sits outside Filament's own tenant-scoped binding. */
    private function findScoped(?int $id): ?Quotation
    {
        if (! $id) {
            return null;
        }

        $quotation = Quotation::find($id);

        if (! $quotation || $quotation->company_id !== $this->company->id) {
            return null;
        }

        return $quotation;
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $base = Quotation::query()->where('company_id', $this->company->id);

        $quotations = (clone $base)
            ->with('client')
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('number', 'like', "%{$this->search}%")
                    ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            }))
            ->orderBy($this->sort['column'], $this->sort['direction'])
            ->paginate(10)
            ->through(fn (Quotation $quotation) => [
                'id' => $quotation->id,
                'number' => $quotation->number ?? '—',
                'client' => $quotation->client?->name ?? '—',
                'quotation_date' => $quotation->quotation_date?->format('d M Y') ?? '—',
                'valid_until' => $quotation->valid_until?->format('d M Y') ?? '—',
                'total' => Money::format((float) $quotation->total, $currency),
                'status' => $quotation->status,
                'status_label' => $quotation->status->getLabel(),
                'status_color' => StatusColor::map($quotation->status->getColor()),
                'viewed_at' => $quotation->viewed_at?->format('d M Y H:i'),
                'portal_key' => $quotation->portal_key,
                'signed_at' => $quotation->signed_at?->format('d M Y H:i'),
                'signed_by_name' => $quotation->signed_by_name,
                'signature' => $quotation->signature,
                'has_signature_image' => $quotation->hasSignatureImage(),
            ]);

        $counts = (clone $base)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $awaitingDecision = (int) ($counts[QuotationStatus::Sent->value] ?? 0);
        $active = (int) $counts->sum() - (int) ($counts[QuotationStatus::Cancelled->value] ?? 0)
            - (int) ($counts[QuotationStatus::Rejected->value] ?? 0)
            - (int) ($counts[QuotationStatus::Expired->value] ?? 0)
            - (int) ($counts[QuotationStatus::Accepted->value] ?? 0);

        $expiringSoon = (clone $base)
            ->where('status', QuotationStatus::Sent)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', now()->addDays(7))
            ->where('valid_until', '>=', now())
            ->count();

        $decided = (int) ($counts[QuotationStatus::Accepted->value] ?? 0)
            + (int) ($counts[QuotationStatus::Rejected->value] ?? 0)
            + (int) ($counts[QuotationStatus::Expired->value] ?? 0);
        $acceptanceRate = $decided > 0
            ? round(((int) ($counts[QuotationStatus::Accepted->value] ?? 0) / $decided) * 100, 1)
            : null;

        $viewingSignature = $this->viewingSignatureId
            ? collect($quotations->items())->firstWhere('id', $this->viewingSignatureId)
            : null;

        return view('livewire.tallstack-quotations', [
            'quotations' => $quotations,
            'viewingSignature' => $viewingSignature,
            'statuses' => QuotationStatus::cases(),
            'stats' => [
                'active' => $active,
                'awaitingDecision' => $awaitingDecision,
                'expiringSoon' => $expiringSoon,
                'acceptanceRate' => $acceptanceRate,
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'quotations',
            'title' => 'Quotations',
        ]);
    }
}
