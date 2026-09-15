<?php

namespace App\Livewire;

use App\Enums\VendorBillStatus;
use App\Models\Company;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Support\Dashboard\Money;
use App\Support\Html\RichTextSanitizer;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the Vendors register — see App\Livewire\TallStackQuotations's docblock
 * for the established pattern this follows. Reuses App\Models\Vendor
 * unmodified; a presentation-layer swap only.
 *
 * Vendor is one of the 14 modal-based resources (CLAUDE.md "Modal-based
 * Create/Edit") — VendorResource registers no dedicated create/edit page,
 * so this mirrors that with an in-page create/edit modal rather than a
 * separate route, matching App\Filament\Resources\Vendors\Schemas\VendorForm
 * field-for-field.
 */
#[Layout('components.tallstack.app')]
class TallStackVendors extends Component
{
    use Interactions, WithPagination;

    public Company $company;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?string $name = null;

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $website = null;

    public ?string $address_line_1 = null;

    public ?string $address_line_2 = null;

    public ?string $city = null;

    public ?string $state = null;

    public ?string $postal_code = null;

    public ?string $country_code = null;

    public ?string $notes = null;

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

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $vendor = $this->findScoped($id);

        if (! $vendor) {
            return;
        }

        $this->editingId = $vendor->id;
        $this->name = $vendor->name;
        $this->email = $vendor->email;
        $this->phone = $vendor->phone;
        $this->website = $vendor->website;
        $this->address_line_1 = $vendor->address_line_1;
        $this->address_line_2 = $vendor->address_line_2;
        $this->city = $vendor->city;
        $this->state = $vendor->state;
        $this->postal_code = $vendor->postal_code;
        $this->country_code = $vendor->country_code;
        $this->notes = $vendor->notes;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->notes = app(RichTextSanitizer::class)->sanitize($this->notes);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:2'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($this->editingId) {
            $vendor = $this->findScoped($this->editingId);

            if (! $vendor) {
                return;
            }

            $vendor->update($data);
            $this->toast()->success('Vendor saved.')->send();
        } else {
            $data['company_id'] = $this->company->id;
            Vendor::create($data);
            $this->toast()->success('Vendor created.')->send();
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $vendor = $this->findScoped($id);

        if (! $vendor) {
            return;
        }

        $vendor->delete();
        $this->toast()->success('Vendor deleted.')->send();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = null;
        $this->email = null;
        $this->phone = null;
        $this->website = null;
        $this->address_line_1 = null;
        $this->address_line_2 = null;
        $this->city = null;
        $this->state = null;
        $this->postal_code = null;
        $this->country_code = null;
        $this->notes = null;
    }

    /** Never trust a bare `Vendor::find()` here — always re-check company ownership. */
    private function findScoped(?int $id): ?Vendor
    {
        if (! $id) {
            return null;
        }

        $vendor = Vendor::find($id);

        if (! $vendor || $vendor->company_id !== $this->company->id) {
            return null;
        }

        return $vendor;
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $base = Vendor::query()->where('company_id', $this->company->id);

        $vendors = (clone $base)
            ->withCount(['purchaseOrders', 'bills'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->orderBy('name')
            ->paginate(10)
            ->through(fn (Vendor $vendor) => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'email' => $vendor->email ?? '—',
                'phone' => $vendor->phone ?? '—',
                'purchase_orders_count' => $vendor->purchase_orders_count,
                'bills_count' => $vendor->bills_count,
            ]);

        $outstanding = (float) VendorBill::query()
            ->where('company_id', $this->company->id)
            ->whereIn('status', [VendorBillStatus::Approved, VendorBillStatus::PartiallyPaid])
            ->sum('balance');

        return view('livewire.tallstack-vendors', [
            'vendors' => $vendors,
            'stats' => [
                'total' => (clone $base)->count(),
                'outstanding' => Money::format($outstanding, $currency),
                'billsCount' => VendorBill::query()->where('company_id', $this->company->id)->count(),
            ],
        ])->layoutData([
            'company' => $this->company,
            'active' => 'vendors',
            'title' => 'Vendors',
        ]);
    }
}
