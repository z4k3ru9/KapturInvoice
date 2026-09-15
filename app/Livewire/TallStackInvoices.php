<?php

namespace App\Livewire;

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

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the Invoices register — see App\Livewire\TallStackQuotations's docblock
 * for the established pattern this follows. Reuses App\Models\Invoice and
 * App\Enums\InvoiceStatus exactly as
 * App\Filament\Resources\Invoices\Tables\InvoicesTable does; every status
 * transition/mutation lives on the detail page
 * (App\Livewire\TallStackInvoiceForm) via the same App\Actions\Billing\*
 * classes, never reimplemented here.
 *
 * Scoped to `type = InvoiceType::Invoice` rows only — the `invoices` table
 * also holds legacy type=Quote rows (App\Filament\Resources\Quotes) and
 * `is_recurring` template rows (App\Filament\Resources\RecurringInvoices),
 * which are separate Filament resources/nav entries and stay there for
 * this phase; do not conflate them into this register.
 */
#[Layout('components.tallstack.app')]
class TallStackInvoices extends Component
{
    use WithPagination;

    public Company $company;

    public ?string $status = null;

    public string $search = '';

    public array $sort = ['column' => 'invoice_date', 'direction' => 'desc'];

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
