<?php

namespace App\Livewire\Portal;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\PortalLink;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The broader, contact-scoped client portal home
 * (docs/rebuild/specs/06-documents-portal-reporting/Specs.md) — a
 * designated billing contact's `PortalLink` shows their client's complete
 * invoice/receipt/payment history; an ordinary contact's link shows only
 * invoices they have an explicit `App\Models\Invitation` for (the existing
 * "explicitly shared documents" mechanism — see FINALIZED-DECISIONS.md
 * §5).
 *
 * Routed (see routes/web.php) behind the same
 * App\Http\Middleware\ResolveCompanyFromDomain group, and copies
 * App\Livewire\Portal\ViewInvoice's exact defensive guard pattern: a
 * cross-domain, revoked, or expired link 404s indistinguishably from a
 * nonexistent one — see PortalLink::isActive().
 */
#[Layout('layouts.public')]
class ClientPortalHome extends Component
{
    public PortalLink $portalLink;

    /** @var Collection<int, Invoice> */
    public Collection $invoices;

    public function mount(PortalLink $portalLink): void
    {
        $portalLink->loadMissing('client.company', 'contact');

        abort_unless(
            app()->bound('currentCompany') && $portalLink->company_id === app('currentCompany')->id,
            404
        );

        abort_unless($portalLink->isActive(), 404);

        $portalLink->forceFill(['last_viewed_at' => now()])->save();

        $this->portalLink = $portalLink;
        $this->invoices = $this->loadInvoices($portalLink);
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function loadInvoices(PortalLink $portalLink): Collection
    {
        $query = $portalLink->contact->is_billing_contact
            ? $portalLink->client->invoices()->where('type', InvoiceType::Invoice)
            : Invoice::query()
                ->where('client_id', $portalLink->client_id)
                ->where('type', InvoiceType::Invoice)
                ->whereHas('invitations', fn ($q) => $q->where('contact_id', $portalLink->contact_id));

        return $query
            ->with(['payments.receipt', 'invitations' => fn ($q) => $q->where('contact_id', $portalLink->contact_id)])
            ->orderByDesc('invoice_date')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.portal.client-portal-home');
    }
}
