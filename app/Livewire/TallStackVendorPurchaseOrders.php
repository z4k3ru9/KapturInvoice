<?php

namespace App\Livewire;

use App\Actions\Procurement\ApproveVendorPurchaseOrder;
use App\Actions\Procurement\ForceDeleteVendorPurchaseOrder;
use App\Enums\VendorPurchaseOrderStatus;
use App\Models\Company;
use App\Models\VendorPurchaseOrder;
use App\Support\Dashboard\Money;
use App\Support\TallStack\StatusColor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native rendering of the Vendor Purchase Orders register —
 * see App\Livewire\TallStackQuotations's docblock for the established
 * pattern. Reuses App\Models\VendorPurchaseOrder/
 * App\Enums\VendorPurchaseOrderStatus and
 * App\Actions\Procurement\ApproveVendorPurchaseOrder unmodified — a
 * presentation-layer swap only.
 */
#[Layout('components.tallstack.app')]
class TallStackVendorPurchaseOrders extends Component
{
    use Interactions, WithPagination;

    public Company $company;

    public ?string $status = null;

    public string $search = '';

    /** Optional pre-filter from a Vendor register row link ("?vendor="). */
    #[Url]
    public ?string $vendor = null;

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

    public function approve(int $id): void
    {
        $po = $this->findScoped($id);

        if (! $po) {
            return;
        }

        try {
            app(ApproveVendorPurchaseOrder::class)->approve($po);
            $this->toast()->success('Vendor purchase order approved.')->send();
        } catch (\RuntimeException $e) {
            $this->toast()->error('Could not approve vendor purchase order', $e->getMessage())->send();
        }
    }

    public function forceDelete(int $id): void
    {
        $po = $this->findScoped($id);

        if (! $po) {
            return;
        }

        try {
            app(ForceDeleteVendorPurchaseOrder::class)->forceDelete($po, auth()->user());
            $this->toast()->success('Vendor purchase order permanently deleted.')->send();
        } catch (\RuntimeException $e) {
            $this->toast()->error('Could not delete vendor purchase order', $e->getMessage())->send();
        }
    }

    private function findScoped(?int $id): ?VendorPurchaseOrder
    {
        if (! $id) {
            return null;
        }

        $po = VendorPurchaseOrder::find($id);

        if (! $po || $po->company_id !== $this->company->id) {
            return null;
        }

        return $po;
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $base = VendorPurchaseOrder::query()->where('company_id', $this->company->id);

        $pos = (clone $base)
            ->with('vendor')
            ->withSum('variances', 'amount')
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->vendor, fn ($q) => $q->where('vendor_id', $this->vendor))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('number', 'like', "%{$this->search}%")
                    ->orWhereHas('vendor', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            }))
            ->orderByDesc('po_date')
            ->paginate(10)
            ->through(fn (VendorPurchaseOrder $po) => [
                'id' => $po->id,
                'number' => $po->number ?? '—',
                'vendor' => $po->vendor?->name ?? '—',
                'po_date' => $po->po_date?->format('d M Y') ?? '—',
                'total' => Money::format((float) $po->total, $currency),
                // withSum() above avoids paymentCeiling()'s own
                // variances()->sum() query per row - same formula,
                // round((float) $this->total + (float) variances sum, 2).
                'payment_ceiling' => Money::format(round((float) $po->total + (float) ($po->variances_sum_amount ?? 0), 2), $currency),
                'status' => $po->status,
                'status_label' => $po->status->getLabel(),
                'status_color' => StatusColor::map($po->status->getColor()),
            ]);

        $counts = (clone $base)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $draft = (int) ($counts[VendorPurchaseOrderStatus::Draft->value] ?? 0);
        $approved = (int) ($counts[VendorPurchaseOrderStatus::Approved->value] ?? 0);
        $totalValue = (float) (clone $base)->where('status', VendorPurchaseOrderStatus::Approved)->sum('total');

        return view('livewire.tallstack-vendor-purchase-orders', [
            'purchaseOrders' => $pos,
            'statuses' => VendorPurchaseOrderStatus::cases(),
            'stats' => [
                'draft' => $draft,
                'approved' => $approved,
                'totalValue' => Money::format($totalValue, $currency),
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'vendor-purchase-orders',
            'title' => 'Vendor Purchase Orders',
        ]);
    }
}
