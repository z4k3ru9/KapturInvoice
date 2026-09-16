<?php

namespace App\Livewire;

use App\Actions\Billing\ForceDeleteInvoice;
use App\Actions\Shared\PlaceHold;
use App\Actions\Shared\ReleaseHold;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Invoice;
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
 * the Invoices register — see App\Livewire\TallStackQuotations's docblock
 * for the established pattern this follows. Reuses App\Models\Invoice and
 * App\Enums\InvoiceStatus exactly as the equivalent pre-TallStackUI
 * Filament invoices table did; every status transition/mutation lives on
 * the detail page
 * (App\Livewire\TallStackInvoiceForm) via the same App\Actions\Billing\*
 * classes, never reimplemented here.
 *
 * Scoped to `type = InvoiceType::Invoice` rows only — the `invoices` table
 * also holds legacy type=Quote rows (no rebuilt TallStack register — see
 * memory.md) and `is_recurring` template rows
 * (App\Livewire\TallStackRecurringInvoices), which stay out of this
 * register; do not conflate them into it.
 *
 * The row-level "Force delete" action (Draft rows only) delegates its
 * guard/authorization entirely to App\Actions\Billing\ForceDeleteInvoice —
 * see that class's docblock for why this narrow capability exists at all.
 */
#[Layout('components.tallstack.app')]
class TallStackInvoices extends Component
{
    use Interactions, WithPagination;

    public Company $company;

    public ?string $status = null;

    public string $search = '';

    public array $sort = ['column' => 'invoice_date', 'direction' => 'desc'];

    public bool $showHoldModal = false;

    public ?int $holdingId = null;

    public string $holdReason = '';

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

    public function forceDelete(int $id): void
    {
        $invoice = $this->findScoped($id);

        if (! $invoice) {
            return;
        }

        try {
            app(ForceDeleteInvoice::class)->forceDelete($invoice, auth()->user());
            $this->toast()->success('Invoice permanently deleted.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not delete invoice', $e->getMessage())->send();
        }
    }

    // --- Hold / Release hold ----------------------------------------------
    // The override lever for the status-transition automation
    // (App\Console\Commands\MarkInvoicesOverdue,
    // App\Console\Commands\GenerateDueRecurringInvoices): pausing
    // automation on one specific invoice for a real reason (moderation, a
    // pending revision), never a raw status pick — see
    // App\Models\Concerns\Holdable's own docblock.

    public function openHoldModal(int $id): void
    {
        $this->holdingId = $id;
        $this->holdReason = '';
        $this->showHoldModal = true;
    }

    public function placeHold(): void
    {
        $invoice = $this->findScoped($this->holdingId);

        if (! $invoice) {
            return;
        }

        try {
            app(PlaceHold::class)->hold($invoice, auth()->user(), $this->holdReason);
            $this->showHoldModal = false;
            $this->toast()->success('Invoice held.', 'Automation is paused on this invoice until the hold is released.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not place hold', $e->getMessage())->send();
        }
    }

    public function releaseHold(int $id): void
    {
        $invoice = $this->findScoped($id);

        if (! $invoice) {
            return;
        }

        try {
            app(ReleaseHold::class)->release($invoice, auth()->user());
            $this->toast()->success('Hold released.', 'Automation will resume on this invoice.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not release hold', $e->getMessage())->send();
        }
    }

    /** Never trust a bare `Invoice::find()` here — always re-check company ownership. */
    private function findScoped(?int $id): ?Invoice
    {
        if (! $id) {
            return null;
        }

        $invoice = Invoice::find($id);

        if (! $invoice || $invoice->company_id !== $this->company->id || $invoice->type !== InvoiceType::Invoice) {
            return null;
        }

        return $invoice;
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $base = Invoice::query()
            ->where('company_id', $this->company->id)
            ->where('type', InvoiceType::Invoice)
            ->where('is_recurring', false);

        $invoices = (clone $base)
            ->with('client')
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('number', 'like', "%{$this->search}%")
                    ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            }))
            ->orderBy($this->sort['column'], $this->sort['direction'])
            ->paginate(10)
            ->through(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number ?? '—',
                'client' => $invoice->client?->name ?? '—',
                'invoice_date' => $invoice->invoice_date?->format('d M Y') ?? '—',
                'due_date' => $invoice->due_date?->format('d M Y') ?? '—',
                'total' => Money::format((float) $invoice->total, $currency),
                'balance' => Money::format((float) $invoice->balance, $currency),
                'status' => $invoice->status,
                'status_label' => $invoice->status->getLabel(),
                'status_color' => StatusColor::map($invoice->status->getColor()),
                'is_held' => $invoice->isHeld(),
                'held_reason' => $invoice->held_reason,
            ]);

        $outstanding = (clone $base)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Partial, InvoiceStatus::Overdue])
            ->sum('balance');

        $overdueCount = (clone $base)
            ->where('status', InvoiceStatus::Overdue)
            ->count();

        $draftCount = (clone $base)
            ->whereIn('status', [InvoiceStatus::Draft, InvoiceStatus::Approved])
            ->count();

        $paidThisMonth = (clone $base)
            ->where('status', InvoiceStatus::Paid)
            ->where('updated_at', '>=', now()->startOfMonth())
            ->sum('total');

        return view('livewire.tallstack-invoices', [
            'invoices' => $invoices,
            'statuses' => InvoiceStatus::cases(),
            'stats' => [
                'outstanding' => Money::format((float) $outstanding, $currency),
                'overdueCount' => $overdueCount,
                'draftCount' => $draftCount,
                'paidThisMonth' => Money::format((float) $paidThisMonth, $currency),
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'invoices',
            'title' => 'Invoices',
        ]);
    }
}
