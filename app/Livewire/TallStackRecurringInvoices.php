<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Invoice;
use App\Services\InvoiceDuplicator;
use App\Support\Dashboard\Money;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the Recurring Invoices register — see App\Livewire\TallStackDashboard's
 * docblock for the established pattern this follows. Reuses
 * App\Models\Invoice filtered to `is_recurring = true` — the recurring
 * *template* rows, not their generated instances — exactly the same
 * scope App\Filament\Resources\RecurringInvoices\RecurringInvoiceResource::
 * getEloquentQuery() applies. This is a presentation-layer swap only; no
 * new column, Action, or Service was added for this page.
 *
 * ⚠️ Deliberately does NOT ship a Pause/Resume action. The Filament
 * resource has no pause/resume mechanism today: `RecurringInvoicesTable`'s
 * only custom row action is "Generate now"
 * (App\Services\InvoiceDuplicator::generateRecurringInstance()), and that
 * action runs unconditionally — it never checks the template's `status`
 * field or anything else before generating. The template's own `status`
 * (Draft/Sent/.../Cancelled, shared with plain Invoice's InvoiceForm) is
 * editable from the Edit page, but setting it to e.g. Cancelled would not
 * actually stop "Generate now" from working — there is no enforcement
 * anywhere in the domain layer. Building a Pause/Resume toggle on top of
 * that field would visually promise a real pause that doesn't exist, so
 * per this phase's explicit instruction ("do not invent a different
 * [mechanism]"), it is left out. The "Status" column below is therefore a
 * read-only, purely computed Active/Ended label (derived from
 * `recurring_end_date` alone — never a stored or authoritative field),
 * not the Active/Paused/Ended tri-state the Stitch mockup shows. See this
 * phase's handoff report for the full explanation.
 */
#[Layout('components.tallstack.app')]
class TallStackRecurringInvoices extends Component
{
    use Interactions, WithPagination;

    public Company $company;

    public string $search = '';

    public array $sort = ['column' => 'created_at', 'direction' => 'desc'];

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function generateNow(int $id): void
    {
        $template = $this->findScoped($id);

        if (! $template) {
            return;
        }

        $this->authorize('update', $template);

        $invoice = app(InvoiceDuplicator::class)->generateRecurringInstance($template);

        $this->toast()->success('Invoice generated', "Created invoice #{$invoice->number}.")->send();
    }

    /** Never trust a bare `Invoice::find()` here — always re-check company ownership and the `is_recurring` scope, the same explicit guard every other TALL-stack page uses since this route sits outside Filament's own tenant-scoped binding. */
    private function findScoped(?int $id): ?Invoice
    {
        if (! $id) {
            return null;
        }

        $invoice = Invoice::find($id);

        if (! $invoice || $invoice->company_id !== $this->company->id || ! $invoice->is_recurring) {
            return null;
        }

        return $invoice;
    }

    /**
     * Purely a display estimate — Carbon arithmetic over the template's
     * own `recurring_frequency` string and `recurring_last_sent_at`/
     * `recurring_start_date`, never a stored schedule and never consulted
     * by "Generate now" or anything else. No real automatic-generation
     * scheduler exists in this codebase (flagged in this phase's report)
     * — this only tells an admin roughly when the next manual "Generate
     * now" would be due if they kept to the stated cadence.
     */
    private function estimateNextDate(Invoice $template): ?Carbon
    {
        $anchor = $template->recurring_last_sent_at?->copy() ?? $template->recurring_start_date?->copy();

        if (! $anchor) {
            return null;
        }

        $next = match (strtolower((string) $template->recurring_frequency)) {
            'weekly' => $anchor->addWeek(),
            'monthly' => $anchor->addMonthNoOverflow(),
            'quarterly' => $anchor->addMonthsNoOverflow(3),
            'annually', 'yearly' => $anchor->addYearNoOverflow(),
            default => null,
        };

        if ($next && $template->recurring_end_date && $next->greaterThan($template->recurring_end_date)) {
            return null;
        }

        return $next;
    }

    /**
     * A cadence label, not a lifecycle status — deliberately not run
     * through App\Support\TallStack\StatusColor::map(). 'primary' (the
     * tenant's own brand color, reserved for the page's one main commit
     * action per AppServiceProvider::registerActionColorPalette()) was
     * previously reused here for "Annually", which both collides with
     * that reserved meaning and made this one frequency's color vary by
     * tenant for no reason tied to its own meaning — swapped for
     * 'indigo', a plain categorical palette entry with no semantic role
     * elsewhere in this app.
     */
    private function frequencyBadge(?string $frequency): array
    {
        return match (strtolower((string) $frequency)) {
            'weekly' => ['label' => 'Weekly', 'color' => 'blue'],
            'monthly' => ['label' => 'Monthly', 'color' => 'green'],
            'quarterly' => ['label' => 'Quarterly', 'color' => 'amber'],
            'annually', 'yearly' => ['label' => 'Annually', 'color' => 'indigo'],
            default => ['label' => $frequency ?: '—', 'color' => 'gray'],
        };
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $base = Invoice::query()
            ->where('company_id', $this->company->id)
            ->where('is_recurring', true);

        $templates = (clone $base)
            ->with('client')
            ->withCount('generatedInvoices')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('number', 'like', "%{$this->search}%")
                    ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            }))
            ->orderBy($this->sort['column'], $this->sort['direction'])
            ->paginate(10)
            ->through(function (Invoice $template) use ($currency) {
                $ended = $template->recurring_end_date && $template->recurring_end_date->isPast();
                $nextDate = $this->estimateNextDate($template);
                $freq = $this->frequencyBadge($template->recurring_frequency);

                return [
                    'id' => $template->id,
                    'number' => $template->number ?? '—',
                    'client' => $template->client?->name ?? '—',
                    'frequency_label' => $freq['label'],
                    'frequency_color' => $freq['color'],
                    'next_date' => $nextDate?->format('d M Y') ?? '—',
                    'amount' => Money::format((float) $template->total, $template->currency_code ?: $currency),
                    'status_label' => $ended ? 'Ended' : 'Active',
                    'status_color' => $ended ? 'gray' : 'green',
                    'auto_bill' => $template->auto_bill,
                    'generated_count' => $template->generated_invoices_count,
                ];
            });

        $activeCount = (int) (clone $base)->where(function ($q) {
            $q->whereNull('recurring_end_date')->orWhere('recurring_end_date', '>=', now()->toDateString());
        })->count();
        $endedCount = (int) (clone $base)->whereNotNull('recurring_end_date')->where('recurring_end_date', '<', now()->toDateString())->count();
        $autoBillCount = (int) (clone $base)->where('auto_bill', true)->count();
        $totalGenerated = (int) Invoice::query()
            ->where('company_id', $this->company->id)
            ->whereNotNull('recurring_template_id')
            ->count();

        return view('livewire.tallstack-recurring-invoices', [
            'templates' => $templates,
            'stats' => [
                'active' => $activeCount,
                'ended' => $endedCount,
                'autoBill' => $autoBillCount,
                'totalGenerated' => $totalGenerated,
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'recurring-invoices',
            'title' => 'Recurring Invoices',
        ]);
    }
}
