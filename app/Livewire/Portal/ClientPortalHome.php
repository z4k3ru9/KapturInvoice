<?php

namespace App\Livewire\Portal;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\PortalLink;
use Carbon\CarbonInterface;
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
            ? $portalLink->client->invoices()
            : Invoice::query()
                ->where('client_id', $portalLink->client_id)
                ->whereHas('invitations', fn ($q) => $q->where('contact_id', $portalLink->contact_id));

        return $query
            // Client-facing documents only — never quotes or recurring
            // invoice templates (App\Filament\Resources\RecurringInvoices'
            // `is_recurring = true` rows are templates, not real invoices).
            ->where('type', InvoiceType::Invoice)
            ->where('is_recurring', false)
            // Payments recorded through the current receivables workflow
            // (App\Actions\Receivables\RecordCustomerPayment) deliberately
            // leave `payments.invoice_id` null and associate invoices only
            // through `payment_allocations` — eager-loading only the
            // legacy `Invoice::payments()` relation omitted every such
            // payment (and its receipt) from this billing-history view
            // even though the invoice's own balance already reflects it (a
            // Codex review finding on PR #4). Legacy InvoiceNinja-imported
            // payments (App\Console\Commands\ImportInvoiceNinjaV4/V5) go
            // the other way — a direct `payments.invoice_id` FK and no
            // allocation row at all — so both must be loaded; see
            // `paymentEvents()` on the view, which merges them without
            // double-counting.
            ->with([
                'payments.receipt',
                'allocations' => fn ($q) => $q->where('is_active', true)->with('payment.receipt'),
                'invitations' => fn ($q) => $q->where('contact_id', $portalLink->contact_id),
            ])
            ->orderByDesc('invoice_date')
            ->get();
    }

    /**
     * Merges this invoice's legacy direct-linked payments
     * (`payments.invoice_id`, from the InvoiceNinja importers) with its
     * active allocation-based payments (the current receivables
     * workflow) into one normalized, chronological list — a payment can
     * appear via only one of the two paths in practice (see the
     * `loadInvoices()` docblock), so no de-duplication is needed beyond
     * keying by payment id.
     *
     * @return list<array{date: ?CarbonInterface, method: ?string, amount: float, receipt_number: ?string}>
     */
    public function paymentEventsFor(Invoice $invoice): array
    {
        $events = [];

        foreach ($invoice->payments as $payment) {
            $events[$payment->id] = [
                'date' => $payment->payment_date ?? $payment->created_at,
                'method' => $payment->method,
                'amount' => (float) $payment->amount,
                'receipt_number' => $payment->receipt?->number,
            ];
        }

        foreach ($invoice->allocations as $allocation) {
            $payment = $allocation->payment;

            if (! $payment || isset($events[$payment->id])) {
                continue;
            }

            $events[$payment->id] = [
                'date' => $payment->payment_date ?? $payment->created_at,
                'method' => $payment->method,
                'amount' => (float) $allocation->amount,
                'receipt_number' => $payment->receipt?->number,
            ];
        }

        $events = array_values($events);

        usort($events, fn ($a, $b) => ($a['date'] ?? now()) <=> ($b['date'] ?? now()));

        return $events;
    }

    public function render(): View
    {
        return view('livewire.portal.client-portal-home');
    }
}
