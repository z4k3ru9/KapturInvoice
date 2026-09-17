<?php

namespace App\Livewire;

use App\Enums\ProposalStatus;
use App\Models\Company;
use App\Models\Proposal;
use App\Support\Dashboard\Money;
use App\Support\TallStack\StatusColor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the Proposals register — see App\Livewire\TallStackQuotations's docblock
 * for the established pattern this follows. Reuses App\Models\Proposal
 * and App\Enums\ProposalStatus unmodified; the "Mark accepted"/"Mark
 * declined" actions mirror the equivalent pre-TallStackUI Filament
 * proposals table's own row actions exactly (a direct forceFill()+save()
 * there too — there
 * is no dedicated Sales/Billing Action class for these two transitions,
 * so none is invented here either).
 *
 * Proposal Templates and Proposal Snippets (the two supporting library
 * resources) are now real TallStackUI pages —
 * App\Livewire\TallStackProposalTemplates/TallStackProposalSnippets. The
 * secondary links below open those instead of the Filament admin pages.
 */
#[Layout('components.tallstack.app')]
class TallStackProposals extends Component
{
    use Interactions, WithPagination;

    public Company $company;

    public ?string $status = null;

    public string $search = '';

    public array $sort = ['column' => 'created_at', 'direction' => 'desc'];

    public int $quantity = 10;

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        // Same reasoning as TallStackQuotations::mount() — Resource::getUrl()
        // needs a resolved panel + tenant even outside a real panel request.
        app(Tenancy::class)->set($company);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function filterStatus(?string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function markAccepted(int $id): void
    {
        $proposal = $this->findScoped($id);

        if (! $proposal) {
            return;
        }

        $this->authorize('update', $proposal);

        $proposal->forceFill([
            'status' => ProposalStatus::Accepted,
            'responded_at' => now(),
        ])->save();

        $this->toast()->success('Proposal marked accepted.')->send();
    }

    public function markDeclined(int $id): void
    {
        $proposal = $this->findScoped($id);

        if (! $proposal) {
            return;
        }

        $this->authorize('update', $proposal);

        $proposal->forceFill([
            'status' => ProposalStatus::Declined,
            'responded_at' => now(),
        ])->save();

        $this->toast()->success('Proposal marked declined.')->send();
    }

    /**
     * Creates a new Draft proposal copying the source's title/client/
     * amount/valid_until/html/css — never its status, invoice_id, or
     * sent/viewed/responded timestamps, since a duplicate is always a
     * fresh, unconverted document. Mirrors the shape of
     * App\Services\InvoiceDuplicator (a plain new-record copy, no shared
     * numbering sequence to consume since Proposal carries no `number`
     * column).
     */
    public function duplicate(int $id): void
    {
        $proposal = $this->findScoped($id);

        if (! $proposal) {
            return;
        }

        $this->authorize('create', Proposal::class);

        $copy = Proposal::create([
            'company_id' => $this->company->id,
            'client_id' => $proposal->client_id,
            'proposal_template_id' => $proposal->proposal_template_id,
            'title' => $proposal->title.' (copy)',
            'html' => $proposal->html,
            'css' => $proposal->css,
            'status' => ProposalStatus::Draft,
            'amount' => $proposal->amount,
            'valid_until' => $proposal->valid_until,
        ]);

        $this->toast()->success('Proposal duplicated.')->send();

        $this->redirect(route('tallstack.proposals.edit', [$this->company, $copy]), navigate: false);
    }

    /** Never trust a bare `Proposal::find()` here — always re-check company ownership, the same explicit guard every other TALL-stack page uses since this route sits outside Filament's own tenant-scoped binding. */
    private function findScoped(?int $id): ?Proposal
    {
        if (! $id) {
            return null;
        }

        $proposal = Proposal::find($id);

        if (! $proposal || $proposal->company_id !== $this->company->id) {
            return null;
        }

        return $proposal;
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $base = Proposal::query()->where('proposals.company_id', $this->company->id);

        $proposals = (clone $base)
            // A Proposal's client_id is nullable, so this must be a
            // leftJoin — an inner join would silently drop any
            // clientless proposal from the paginated result.
            ->leftJoin('clients', 'clients.id', '=', 'proposals.client_id')
            ->select('proposals.*')
            ->with(['client', 'invoice'])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            }))
            ->when($this->sort['column'] === 'client',
                fn ($q) => $q->orderBy('clients.name', $this->sort['direction']),
                fn ($q) => $q->orderBy('proposals.'.$this->sort['column'], $this->sort['direction'])
            )
            ->paginate($this->quantity)
            ->through(fn (Proposal $proposal) => [
                'id' => $proposal->id,
                'title' => $proposal->title,
                'client' => $proposal->client?->name ?? '—',
                'amount' => Money::format((float) $proposal->amount, $currency),
                'valid_until' => $proposal->valid_until?->format('d M Y') ?? '—',
                'invoice_number' => $proposal->invoice?->number,
                'status' => $proposal->status,
                'status_label' => $proposal->status->getLabel(),
                'status_color' => StatusColor::map($proposal->status->getColor()),
            ]);

        return view('livewire.tallstack-proposals', [
            'proposals' => $proposals,
            'statuses' => ProposalStatus::cases(),
        ])->layoutData([
            'company' => $this->company,
            'active' => 'proposals',
            'title' => 'Proposals',
        ]);
    }
}
