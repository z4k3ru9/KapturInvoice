<?php

namespace App\Livewire;

use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Support\Dashboard\Money;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The shell's global search box (resources/views/components/tallstack/app.blade.php)
 * — was a plain, entirely unwired `<input>` before this: no wire:model, no
 * backend, purely decorative. Embedded as its own Livewire component
 * (`<livewire:global-search :company="$company" />`) since the shell
 * itself is a plain Blade component with no Livewire state of its own.
 *
 * Results are grouped into one section per document type (matching the
 * user's own ask: "showing result by section... invoices, quotes, jobs,
 * proposal"), each row showing the client's name as the primary line and
 * a "number · date · total" description underneath. Every query relies
 * on App\Models\Concerns\BelongsToCompany's own global scope for tenant
 * isolation — by the time this component renders, the page's own
 * mount() has already called app(Tenancy::class)->set($company), so no
 * extra company_id filter is added here (matching every other
 * TallStack{Thing} list component's own convention).
 */
class GlobalSearch extends Component
{
    public Company $company;

    public string $query = '';

    /** Below this length, showing results would mostly just be noise (near-every-row matches). */
    private const MIN_QUERY_LENGTH = 2;

    private const RESULTS_PER_SECTION = 5;

    public function render(): View
    {
        $term = trim($this->query);
        $currency = $this->company->currency_code;

        $sections = [];

        if (mb_strlen($term) >= self::MIN_QUERY_LENGTH) {
            $sections[] = $this->invoiceSection($term, $currency);
            $sections[] = $this->quotationSection($term, $currency);
            $sections[] = $this->jobSection($term, $currency);
            $sections[] = $this->proposalSection($term, $currency);

            $sections = array_values(array_filter($sections, fn (array $section) => $section['results']->isNotEmpty()));
        }

        return view('livewire.global-search', ['sections' => $sections]);
    }

    private function invoiceSection(string $term, string $currency): array
    {
        $results = Invoice::query()
            ->where('type', InvoiceType::Invoice)
            ->where('is_recurring', false)
            ->with('client')
            ->where(fn ($q) => $q->where('number', 'like', "%{$term}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$term}%")))
            ->latest('invoice_date')
            ->limit(self::RESULTS_PER_SECTION)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'url' => route('tallstack.invoices.edit', [$this->company, $invoice->id]),
                'client' => $invoice->client?->name ?? '—',
                'description' => $this->description($invoice->number, $invoice->invoice_date?->format('d M Y'), Money::format((float) $invoice->total, $currency)),
            ]);

        return ['label' => 'Invoices', 'icon' => 'document-currency-dollar', 'results' => $results];
    }

    private function quotationSection(string $term, string $currency): array
    {
        $results = Quotation::query()
            ->with('client')
            ->where(fn ($q) => $q->where('number', 'like', "%{$term}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$term}%")))
            ->latest('quotation_date')
            ->limit(self::RESULTS_PER_SECTION)
            ->get()
            ->map(fn (Quotation $quotation) => [
                'url' => route('tallstack.quotations.edit', [$this->company, $quotation->id]),
                'client' => $quotation->client?->name ?? '—',
                'description' => $this->description($quotation->number, $quotation->quotation_date?->format('d M Y'), Money::format((float) $quotation->total, $currency)),
            ]);

        return ['label' => 'Quotations', 'icon' => 'document-text', 'results' => $results];
    }

    private function jobSection(string $term, string $currency): array
    {
        $results = SalesOrder::query()
            ->with('client')
            ->where(fn ($q) => $q->where('number', 'like', "%{$term}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$term}%")))
            ->latest('created_at')
            ->limit(self::RESULTS_PER_SECTION)
            ->get()
            ->map(fn (SalesOrder $job) => [
                'url' => route('tallstack.jobs.show', [$this->company, $job->id]),
                'client' => $job->client?->name ?? '—',
                'description' => $this->description($job->number, $job->created_at?->format('d M Y'), Money::format((float) $job->approved_value, $currency)),
            ]);

        return ['label' => 'Jobs', 'icon' => 'briefcase', 'results' => $results];
    }

    private function proposalSection(string $term, string $currency): array
    {
        // Proposal has no document-number sequence (never wired into
        // DocumentNumberGenerator, see CLAUDE.md's Proposals section) —
        // its own title stands in for "number" in the description line.
        $results = Proposal::query()
            ->with('client')
            ->where(fn ($q) => $q->where('title', 'like', "%{$term}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$term}%")))
            ->latest('created_at')
            ->limit(self::RESULTS_PER_SECTION)
            ->get()
            ->map(fn (Proposal $proposal) => [
                'url' => route('tallstack.proposals.edit', [$this->company, $proposal->id]),
                'client' => $proposal->client?->name ?? '—',
                'description' => $this->description($proposal->title, $proposal->created_at?->format('d M Y'), Money::format((float) $proposal->amount, $currency)),
            ]);

        return ['label' => 'Proposals', 'icon' => 'presentation-chart-bar', 'results' => $results];
    }

    private function description(?string $identifier, ?string $date, ?string $amount): string
    {
        return collect([$identifier, $date, $amount])->filter()->implode(' · ');
    }
}
