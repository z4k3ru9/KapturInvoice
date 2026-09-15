<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\HandoverReport;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The TALL-stack-native Handover Reports register — browses every
 * App\Models\HandoverReport across ALL jobs for the tenant, read-only-
 * oriented per its own field set (number, date, override flag/reason,
 * recorded-by — nothing else to drill into that isn't already on this
 * row, unlike a Delivery Order's line items). See
 * App\Livewire\TallStackDeliveryOrders' docblock for why this
 * deliberately has no separate detail page or "Record handover" action:
 * that's still App\Actions\Delivery\CompleteHandover, wired inline on
 * App\Livewire\TallStackSalesOrder's Delivery tab. Each row links back to
 * the owning job's workspace instead.
 */
#[Layout('components.tallstack.app')]
class TallStackHandoverReports extends Component
{
    use WithPagination;

    public Company $company;

    public string $search = '';

    public array $sort = ['column' => 'handover_date', 'direction' => 'desc'];

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company, isQuiet: true);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $base = HandoverReport::query()->where('company_id', $this->company->id);

        $handoverReports = (clone $base)
            ->with(['salesOrder.client', 'createdBy'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('number', 'like', "%{$this->search}%")
                    ->orWhereHas('salesOrder', fn ($q) => $q->where('number', 'like', "%{$this->search}%"))
                    ->orWhereHas('salesOrder.client', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            }))
            ->orderBy($this->sort['column'], $this->sort['direction'])
            ->paginate(10)
            ->through(fn (HandoverReport $h) => [
                'id' => $h->id,
                'number' => $h->number,
                'job_id' => $h->salesOrder?->id,
                'job_number' => $h->salesOrder?->number ?? '—',
                'client' => $h->salesOrder?->client?->name ?? '—',
                'handover_date' => $h->handover_date?->format('d M Y') ?? '—',
                'is_override' => $h->is_override,
                'override_reason' => $h->override_reason ?: '—',
                'notes' => $h->notes ?: '—',
                'created_by' => $h->createdBy?->name ?? '—',
            ]);

        $thisMonthStart = now()->startOfMonth();

        return view('livewire.tallstack-handover-reports', [
            'handoverReports' => $handoverReports,
            'stats' => [
                'total' => (int) (clone $base)->count(),
                'thisMonth' => (int) (clone $base)->where('handover_date', '>=', $thisMonthStart)->count(),
                'overrides' => (int) (clone $base)->where('is_override', true)->count(),
                'distinctJobs' => (int) (clone $base)->distinct('sales_order_id')->count('sales_order_id'),
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'handover-reports',
            'title' => 'Handover Reports',
        ]);
    }
}
