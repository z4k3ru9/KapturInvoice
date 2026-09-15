<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\DeliveryOrder;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The TALL-stack-native Delivery Orders register — browses every
 * App\Models\DeliveryOrder across ALL jobs for the tenant, not just one
 * job's tab. See App\Livewire\TallStackQuotations' docblock for the
 * established list-page pattern this follows.
 *
 * A DeliveryOrder is only ever created through
 * App\Actions\Delivery\CompleteDelivery, already wired inline on
 * App\Livewire\TallStackSalesOrder's Delivery tab (the "Record delivery"
 * modal) — this page is deliberately a read/browse surface only and does
 * not duplicate that action. Each row links to
 * App\Livewire\TallStackDeliveryOrder's detail page for the full line-item
 * breakdown, and from there back to the owning job's workspace.
 */
#[Layout('components.tallstack.app')]
class TallStackDeliveryOrders extends Component
{
    use WithPagination;

    public Company $company;

    public string $search = '';

    public array $sort = ['column' => 'delivery_date', 'direction' => 'desc'];

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
        $base = DeliveryOrder::query()->where('company_id', $this->company->id);

        $deliveryOrders = (clone $base)
            ->with(['salesOrder.client', 'items', 'createdBy'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('number', 'like', "%{$this->search}%")
                    ->orWhereHas('salesOrder', fn ($q) => $q->where('number', 'like', "%{$this->search}%"))
                    ->orWhereHas('salesOrder.client', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            }))
            ->orderBy($this->sort['column'], $this->sort['direction'])
            ->paginate(10)
            ->through(fn (DeliveryOrder $d) => [
                'id' => $d->id,
                'number' => $d->number,
                'job_id' => $d->salesOrder?->id,
                'job_number' => $d->salesOrder?->number ?? '—',
                'client' => $d->salesOrder?->client?->name ?? '—',
                'delivery_date' => $d->delivery_date?->format('d M Y') ?? '—',
                'items_count' => $d->items->count(),
                'created_by' => $d->createdBy?->name ?? '—',
            ]);

        $thisMonthStart = now()->startOfMonth();
        $thisWeekStart = now()->startOfWeek();

        return view('livewire.tallstack-delivery-orders', [
            'deliveryOrders' => $deliveryOrders,
            'stats' => [
                'total' => (int) (clone $base)->count(),
                'thisMonth' => (int) (clone $base)->where('delivery_date', '>=', $thisMonthStart)->count(),
                'thisWeek' => (int) (clone $base)->where('delivery_date', '>=', $thisWeekStart)->count(),
                'distinctJobs' => (int) (clone $base)->distinct('sales_order_id')->count('sales_order_id'),
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'delivery-orders',
            'title' => 'Delivery Orders',
        ]);
    }
}
