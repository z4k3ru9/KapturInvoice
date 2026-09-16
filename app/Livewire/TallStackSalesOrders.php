<?php

namespace App\Livewire;

use App\Actions\Sales\ForceDeleteSalesOrder;
use App\Actions\Shared\PlaceHold;
use App\Actions\Shared\ReleaseHold;
use App\Enums\SalesOrderStatus;
use App\Models\Company;
use App\Models\SalesOrder;
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
 * The TALL-stack-native Job (SalesOrder) register — see
 * App\Livewire\TallStackQuotations' docblock for the established pattern
 * this follows. Reuses App\Models\SalesOrder/App\Enums\SalesOrderStatus
 * exactly; matches the equivalent pre-TallStackUI Filament sales orders
 * table's own column set (number, client, status, approved_value, source
 * quotation, created_at) plus a "next milestone" display column computed
 * here purely for presentation (not a new domain calculation — the
 * milestone rows themselves already exist via PaymentMilestone).
 */
#[Layout('components.tallstack.app')]
class TallStackSalesOrders extends Component
{
    use Interactions, WithPagination;

    public Company $company;

    public ?string $status = null;

    public string $search = '';

    public array $sort = ['column' => 'created_at', 'direction' => 'desc'];

    public bool $showHoldModal = false;

    public ?int $holdingId = null;

    public string $holdReason = '';

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

    public function filterStatus(?string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function forceDelete(int $id): void
    {
        $job = SalesOrder::query()->where('company_id', $this->company->id)->find($id);

        if (! $job) {
            return;
        }

        try {
            app(ForceDeleteSalesOrder::class)->forceDelete($job, auth()->user());
            $this->toast()->success('Job permanently deleted.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not delete job', $e->getMessage())->send();
        }
    }

    // --- Hold / Release hold ----------------------------------------------
    // The override lever for the SalesOrder auto-close-operationally
    // automation (App\Actions\Delivery\CompleteDelivery/CompleteHandover's
    // auto-trigger) — see App\Livewire\TallStackInvoices for the identical
    // pattern this mirrors and App\Models\Concerns\Holdable's own docblock.

    public function openHoldModal(int $id): void
    {
        $this->holdingId = $id;
        $this->holdReason = '';
        $this->showHoldModal = true;
    }

    public function placeHold(): void
    {
        $job = $this->findScoped($this->holdingId);

        if (! $job) {
            return;
        }

        try {
            app(PlaceHold::class)->hold($job, auth()->user(), $this->holdReason);
            $this->showHoldModal = false;
            $this->toast()->success('Job held.', 'Automation is paused on this job until the hold is released.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not place hold', $e->getMessage())->send();
        }
    }

    public function releaseHold(int $id): void
    {
        $job = $this->findScoped($id);

        if (! $job) {
            return;
        }

        try {
            app(ReleaseHold::class)->release($job, auth()->user());
            $this->toast()->success('Hold released.', 'Automation will resume on this job.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not release hold', $e->getMessage())->send();
        }
    }

    /** Never trust a bare `SalesOrder::find()` here — always re-check company ownership. */
    private function findScoped(?int $id): ?SalesOrder
    {
        if (! $id) {
            return null;
        }

        $job = SalesOrder::find($id);

        if (! $job || $job->company_id !== $this->company->id) {
            return null;
        }

        return $job;
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $base = SalesOrder::query()->where('company_id', $this->company->id);

        $jobs = (clone $base)
            ->with(['client', 'quotation', 'milestones' => fn ($q) => $q->orderBy('due_date')])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('number', 'like', "%{$this->search}%")
                    ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            }))
            ->orderBy($this->sort['column'], $this->sort['direction'])
            ->paginate(10)
            ->through(function (SalesOrder $job) use ($currency) {
                $nextMilestone = $job->milestones->firstWhere('due_date', '>=', now()->startOfDay())
                    ?? $job->milestones->first();

                return [
                    'id' => $job->id,
                    'number' => $job->number ?? '—',
                    'client' => $job->client?->name ?? '—',
                    'job_type' => $job->job_type->getLabel(),
                    'approved_value' => Money::format((float) $job->approved_value, $currency),
                    'quotation_number' => $job->quotation?->number ?? '—',
                    'next_milestone' => $nextMilestone
                        ? ($nextMilestone->description ?: $nextMilestone->type->getLabel())
                        : '—',
                    'next_milestone_due' => $nextMilestone?->due_date?->format('d M Y') ?? '—',
                    'status' => $job->status,
                    'status_label' => $job->status->getLabel(),
                    'status_color' => StatusColor::map($job->status->getColor()),
                    'closed' => $job->isFullyClosed(),
                    'is_held' => $job->isHeld(),
                    'held_reason' => $job->held_reason,
                ];
            });

        $counts = (clone $base)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $activeStatuses = [
            SalesOrderStatus::Approved, SalesOrderStatus::Procurement,
            SalesOrderStatus::InProgress, SalesOrderStatus::Delivered, SalesOrderStatus::HandedOver,
        ];

        return view('livewire.tallstack-sales-orders', [
            'jobs' => $jobs,
            'statuses' => SalesOrderStatus::cases(),
            'stats' => [
                'total' => (int) $counts->sum(),
                'active' => (int) collect($activeStatuses)->sum(fn ($s) => (int) ($counts[$s->value] ?? 0)),
                'draft' => (int) ($counts[SalesOrderStatus::Draft->value] ?? 0),
                'closed' => (int) ($counts[SalesOrderStatus::Closed->value] ?? 0),
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'jobs',
            'title' => 'Jobs',
        ]);
    }
}
