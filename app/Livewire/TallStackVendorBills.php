<?php

namespace App\Livewire;

use App\Actions\Procurement\ApproveVendorBill;
use App\Actions\Procurement\ForceDeleteVendorBill;
use App\Actions\Procurement\SubmitVendorBill;
use App\Enums\VendorBillStatus;
use App\Models\Company;
use App\Models\VendorBill;
use App\Support\Dashboard\Money;
use App\Support\TallStack\StatusColor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native rendering of the Vendor Bills register — see
 * App\Livewire\TallStackQuotations's docblock for the established pattern.
 * Reuses App\Models\VendorBill/App\Enums\VendorBillStatus and
 * App\Actions\Procurement\{SubmitVendorBill,ApproveVendorBill} unmodified
 * — a presentation-layer swap only.
 */
#[Layout('components.tallstack.app')]
class TallStackVendorBills extends Component
{
    use Interactions, WithPagination;

    public Company $company;

    public ?string $status = null;

    public string $search = '';

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

    public function submit(int $id): void
    {
        $bill = $this->findScoped($id);

        if (! $bill) {
            return;
        }

        try {
            app(SubmitVendorBill::class)->submit($bill);
            $this->toast()->success('Vendor bill submitted.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not submit vendor bill', $e->getMessage())->send();
        }
    }

    public function approve(int $id): void
    {
        $bill = $this->findScoped($id);

        if (! $bill) {
            return;
        }

        try {
            app(ApproveVendorBill::class)->approve($bill, auth()->user());
            $this->toast()->success('Vendor bill approved.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not approve vendor bill', $e->getMessage())->send();
        }
    }

    public function forceDelete(int $id): void
    {
        $bill = $this->findScoped($id);

        if (! $bill) {
            return;
        }

        try {
            app(ForceDeleteVendorBill::class)->forceDelete($bill, auth()->user());
            $this->toast()->success('Vendor bill permanently deleted.')->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not delete vendor bill', $e->getMessage())->send();
        }
    }

    private function findScoped(?int $id): ?VendorBill
    {
        if (! $id) {
            return null;
        }

        $bill = VendorBill::find($id);

        if (! $bill || $bill->company_id !== $this->company->id) {
            return null;
        }

        return $bill;
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $base = VendorBill::query()->where('company_id', $this->company->id);

        $bills = (clone $base)
            ->with(['vendor', 'vendorPurchaseOrder'])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->vendor, fn ($q) => $q->where('vendor_id', $this->vendor))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('number', 'like', "%{$this->search}%")
                    ->orWhereHas('vendor', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            }))
            ->orderByDesc('bill_date')
            ->paginate(10)
            ->through(fn (VendorBill $bill) => [
                'id' => $bill->id,
                'number' => $bill->number ?? '—',
                'vendor' => $bill->vendor?->name ?? '—',
                'po_number' => $bill->vendorPurchaseOrder?->number ?? '—',
                'bill_date' => $bill->bill_date?->format('d M Y') ?? '—',
                'total' => Money::format((float) $bill->total, $currency),
                'balance' => Money::format((float) $bill->balance, $currency),
                'status' => $bill->status,
                'status_label' => $bill->status->getLabel(),
                'status_color' => StatusColor::map($bill->status->getColor()),
            ]);

        $counts = (clone $base)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $outstandingBalance = (float) (clone $base)
            ->whereIn('status', [VendorBillStatus::Approved, VendorBillStatus::PartiallyPaid])
            ->sum('balance');

        $awaitingApproval = (int) ($counts[VendorBillStatus::Submitted->value] ?? 0);

        return view('livewire.tallstack-vendor-bills', [
            'bills' => $bills,
            'statuses' => VendorBillStatus::cases(),
            'stats' => [
                'draft' => (int) ($counts[VendorBillStatus::Draft->value] ?? 0),
                'awaitingApproval' => $awaitingApproval,
                'outstandingBalance' => Money::format($outstandingBalance, $currency),
                'paid' => (int) ($counts[VendorBillStatus::Paid->value] ?? 0),
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'vendor-bills',
            'title' => 'Vendor Bills',
        ]);
    }
}
