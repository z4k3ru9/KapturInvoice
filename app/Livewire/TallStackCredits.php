<?php

namespace App\Livewire;

use App\Filament\Support\Money;
use App\Models\Company;
use App\Models\Credit;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The TALL-stack-native Credits register — read-only, matching
 * App\Filament\Resources\Credits (see that resource's docblock:
 * "Credits/refunds are deferred launch scope"; and
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §7: "New credit-note creation,
 * editing, refunds, and write-offs remain deferred"). Every imported
 * Credit row is a historical record from InvoiceNinja data migration only
 * — this page deliberately has no create/edit/delete action anywhere,
 * mirroring the Filament resource's own missing 'create'/'edit' pages
 * (there `EditAction`/`CreateAction` still exist only because Filament's
 * generic fallback-to-modal behavior kicks in when no page is registered;
 * this TALL-stack page does not reproduce that quirk — it is View-only by
 * design, not by omission).
 *
 * See App\Livewire\TallStackDeliveryOrders' docblock for the established
 * read/browse-only list pattern this follows.
 */
#[Layout('components.tallstack.app')]
class TallStackCredits extends Component
{
    use WithPagination;

    public Company $company;

    public string $search = '';

    public array $sort = ['column' => 'credit_date', 'direction' => 'desc'];

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        // Same reasoning as TallStackDashboard::mount() — kept for parity
        // with every other TALL-stack page even though nothing on this
        // page currently needs Resource::getUrl().
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company, isQuiet: true);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $base = Credit::query()->where('company_id', $this->company->id);

        $credits = (clone $base)
            ->with(['client', 'invoice'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('number', 'like', "%{$this->search}%")
                    ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                    ->orWhereHas('invoice', fn ($q) => $q->where('number', 'like', "%{$this->search}%"));
            }))
            ->orderBy($this->sort['column'], $this->sort['direction'])
            ->paginate(10)
            ->through(fn (Credit $credit) => [
                'id' => $credit->id,
                'number' => $credit->number ?? '—',
                'client' => $credit->client?->name ?? '—',
                'invoice_id' => $credit->invoice?->id,
                'invoice_number' => $credit->invoice?->number,
                'amount' => Money::format((float) $credit->amount, $currency),
                'credit_date' => $credit->credit_date?->format('d M Y') ?? '—',
            ]);

        $totalAmount = (float) (clone $base)->sum('amount');
        $linkedAmount = (float) (clone $base)->whereNotNull('invoice_id')->sum('amount');
        $unlinkedBalance = (float) (clone $base)->whereNull('invoice_id')->sum('balance');

        return view('livewire.tallstack-credits', [
            'credits' => $credits,
            'stats' => [
                'total' => Money::format($totalAmount, $currency),
                'linked' => Money::format($linkedAmount, $currency),
                'unlinkedBalance' => Money::format($unlinkedBalance, $currency),
                'count' => (int) (clone $base)->count(),
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'credits',
            'title' => 'Credits',
        ]);
    }
}
