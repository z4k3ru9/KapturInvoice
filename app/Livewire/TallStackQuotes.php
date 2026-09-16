<?php

namespace App\Livewire;

use App\Actions\Billing\ForceDeleteQuote;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\BillingMailer;
use App\Services\InvoiceDuplicator;
use App\Support\Dashboard\Money;
use App\Support\TallStack\StatusColor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the legacy Quotes register — `Invoice` rows with
 * `type = InvoiceType::Quote`, on the SAME `invoices` table as real
 * invoices, distinct from the canonical App\Models\Quotation
 * (App\Livewire\TallStackQuotations, its own `quotations` table). See
 * App\Livewire\TallStackInvoices's docblock for why the two stay
 * separate registers, and App\Livewire\TallStackInvoiceForm's docblock
 * for how its detail/edit page is reused — not duplicated — for a
 * quote-type row, since a quote is structurally identical to an invoice
 * until it's converted (the same reasoning the old Filament
 * `QuoteResource::form()` documented when it called
 * `InvoiceForm::configure($schema)` directly).
 *
 * These rows are import-only historical data (docs/data-import.md) — the
 * `Quotation` model is the canonical way to create a NEW quote today, so
 * there is deliberately no "New quote" action here, mirroring the old
 * Filament `QuoteResource`, which never registered a Create page either.
 *
 * Row actions mirror the old Filament `QuotesTable` exactly (retrieved
 * from git history at the Filament-removal commit): Send/Resend
 * (App\Services\BillingMailer::sendQuote(), unrestricted by status, same
 * as the old table action), Convert to invoice
 * (App\Services\InvoiceDuplicator::convertQuoteToInvoice(), also
 * unrestricted — the old action never blocked converting the same quote
 * more than once either), Force delete
 * (App\Actions\Billing\ForceDeleteQuote — Draft, never paid/allocated/
 * corrected, and never already converted), and Download PDF (the same
 * `invoices.pdf` route real invoices use — same underlying model/view).
 */
#[Layout('components.tallstack.app')]
class TallStackQuotes extends Component
{
    use Interactions, WithPagination;

    public Company $company;

    public string $search = '';

    public array $sort = ['column' => 'invoice_date', 'direction' => 'desc'];

    public int $quantity = 10;

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        // Same reasoning as TallStackInvoices::mount() — Resource::getUrl()
        // needs a resolved panel + tenant even outside a real panel request.
        app(Tenancy::class)->set($company);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /** Same App\Services\BillingMailer::sendQuote() the old Filament QuotesTable row action called — never a bespoke email path here. */
    public function send(int $id): void
    {
        $quote = $this->findScoped($id);

        if (! $quote) {
            return;
        }

        $this->authorize('update', $quote);

        try {
            app(BillingMailer::class)->sendQuote($quote);
            $this->toast()->success('Quote sent.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not send quote', $e->getMessage())->send();
        }
    }

    /** Same App\Services\InvoiceDuplicator::convertQuoteToInvoice() the old Filament QuotesTable row action called. */
    public function convertToInvoice(int $id): void
    {
        $quote = $this->findScoped($id);

        if (! $quote) {
            return;
        }

        $this->authorize('update', $quote);

        try {
            $invoice = app(InvoiceDuplicator::class)->convertQuoteToInvoice($quote);
            $this->toast()->success('Converted to invoice.', "Created invoice #{$invoice->number}.")->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not convert quote', $e->getMessage())->send();
        }
    }

    public function forceDelete(int $id): void
    {
        $quote = $this->findScoped($id);

        if (! $quote) {
            return;
        }

        try {
            app(ForceDeleteQuote::class)->forceDelete($quote, auth()->user());
            $this->toast()->success('Quote permanently deleted.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not delete quote', $e->getMessage())->send();
        }
    }

    /** Never trust a bare `Invoice::find()` here — always re-check company ownership and type, same guard as TallStackInvoices::findScoped(). */
    private function findScoped(?int $id): ?Invoice
    {
        if (! $id) {
            return null;
        }

        $quote = Invoice::find($id);

        if (! $quote || $quote->company_id !== $this->company->id || $quote->type !== InvoiceType::Quote) {
            return null;
        }

        return $quote;
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $base = Invoice::query()
            ->where('invoices.company_id', $this->company->id)
            ->where('type', InvoiceType::Quote);

        $quotes = (clone $base)
            ->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->select('invoices.*')
            ->with('client')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('number', 'like', "%{$this->search}%")
                    ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            }))
            ->when($this->sort['column'] === 'client',
                fn ($q) => $q->orderBy('clients.name', $this->sort['direction']),
                fn ($q) => $q->orderBy('invoices.'.$this->sort['column'], $this->sort['direction'])
            )
            ->paginate($this->quantity)
            ->through(fn (Invoice $quote) => [
                'id' => $quote->id,
                'number' => $quote->number ?? '—',
                'client' => $quote->client?->name ?? '—',
                'invoice_date' => $quote->invoice_date?->format('d M Y') ?? '—',
                'total' => Money::format((float) $quote->total, $currency),
                'status' => $quote->status,
                'status_label' => $quote->status->getLabel(),
                'status_color' => StatusColor::map($quote->status->getColor()),
            ]);

        $total = (clone $base)->count();
        $converted = (clone $base)->whereHas('convertedInvoices')->count();

        return view('livewire.tallstack-quotes', [
            'quotes' => $quotes,
            'stats' => [
                'total' => $total,
                'converted' => $converted,
                'notConverted' => $total - $converted,
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'quotes',
            'title' => 'Quotes',
        ]);
    }
}
