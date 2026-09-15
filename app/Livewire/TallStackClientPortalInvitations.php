<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Invitation;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The TALL-stack-native Client Portal Invitations register — read-mostly,
 * matching App\Filament\Resources\Invitations\InvitationResource (see that
 * resource's own docblock: invitations are generated automatically when an
 * invoice is sent, never created by hand here). Distinct from the broader,
 * revocable/expiring App\Models\PortalLink ("Portal Links" relation
 * manager on the Client detail screen) — this one is invoice-scoped and
 * essentially permanent, an audit trail of who was sent what and whether
 * they opened/signed it, so there is no revoke action here either.
 *
 * Invitation has no company_id column of its own (scoped only indirectly,
 * via its invoice) — same reasoning as InvitationResource's own
 * $isScopedToTenant = false, applied here as an explicit whereHas() scope
 * instead of relying on BelongsToCompany.
 *
 * See App\Livewire\TallStackCredits' docblock for the established
 * read/browse-only register pattern this follows, and
 * docs/rebuild/outputs/27-filament-parity-gap-prompts.md §21 for the exact
 * table/filter/empty-state spec.
 */
#[Layout('components.tallstack.app')]
class TallStackClientPortalInvitations extends Component
{
    use WithPagination;

    public Company $company;

    public string $search = '';

    /**
     * null = all, 'viewed' = only invitations that have been opened,
     * 'signed' = only invitations that have been e-signed.
     */
    public ?string $filter = null;

    public array $sort = ['column' => 'created_at', 'direction' => 'desc'];

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        // Same reasoning as TallStackCredits::mount() — kept for parity
        // with every other TALL-stack page even though nothing on this
        // page currently needs Resource::getUrl().
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company, isQuiet: true);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function filterBy(?string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    private function scopedQuery(): Builder
    {
        return Invitation::query()
            ->whereHas('invoice', fn (Builder $q) => $q->where('company_id', $this->company->id));
    }

    public function render(): View
    {
        $base = $this->scopedQuery();

        $invitations = (clone $base)
            ->with(['invoice', 'contact'])
            ->when($this->search, fn (Builder $q) => $q->where(function (Builder $q) {
                $q->whereHas('invoice', fn (Builder $q) => $q->where('number', 'like', "%{$this->search}%"))
                    ->orWhereHas('contact', function (Builder $q) {
                        $q->where('first_name', 'like', "%{$this->search}%")
                            ->orWhere('last_name', 'like', "%{$this->search}%")
                            ->orWhere('email', 'like', "%{$this->search}%");
                    });
            }))
            ->when($this->filter === 'viewed', fn (Builder $q) => $q->whereNotNull('viewed_at'))
            ->when($this->filter === 'signed', fn (Builder $q) => $q->whereNotNull('signed_at'))
            ->orderBy($this->sort['column'], $this->sort['direction'])
            ->paginate(10)
            ->through(fn (Invitation $invitation) => [
                'id' => $invitation->id,
                'key' => $invitation->key,
                'invoice_id' => $invitation->invoice?->id,
                'invoice_number' => $invitation->invoice?->number ?? '—',
                'contact_name' => $invitation->contact?->name ?? '—',
                'contact_email' => $invitation->contact?->email ?? '—',
                'sent_at' => $invitation->sent_at?->diffForHumans(),
                'viewed_at' => $invitation->viewed_at?->diffForHumans(),
                'is_viewed' => filled($invitation->viewed_at),
                'signed_at' => $invitation->signed_at?->diffForHumans(),
                'is_signed' => filled($invitation->signed_at),
            ]);

        return view('livewire.tallstack-client-portal-invitations', [
            'invitations' => $invitations,
            'stats' => [
                'total' => (clone $base)->count(),
                'viewed' => (clone $base)->whereNotNull('viewed_at')->count(),
                'signed' => (clone $base)->whereNotNull('signed_at')->count(),
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'client-portal-invitations',
            'title' => 'Client Portal Invitations',
        ]);
    }
}
