<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Support\Dashboard\Money;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the Clients register — see App\Livewire\TallStackQuotations's docblock
 * for the established pattern this follows. Reuses App\Models\Client
 * unmodified, and mirrors the equivalent pre-TallStackUI Filament client
 * resource's form and table field-for-field: no field is added or dropped.
 *
 * Client keeps Filament's own "no dedicated Create/Edit page" convention
 * (docs/filament-admin-layout-design.md §8 — one of the 14 modal-based
 * resources), which this page mirrors literally with an
 * `<x-modal>`-based create/edit form rather than a separate route.
 */
#[Layout('components.tallstack.app')]
class TallStackClients extends Component
{
    use Interactions, WithPagination;

    public Company $company;

    public string $search = '';

    // --- Create/edit modal state — exactly ClientForm's own field set. ---
    public bool $showClientModal = false;

    public ?int $editingClientId = null;

    public ?string $name = null;

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $website = null;

    public ?string $currency_code = null;

    public ?string $tax_number = null;

    public ?string $id_number = null;

    public ?string $legacy_client_id = null;

    public float $default_discount = 0;

    public bool $default_discount_is_percentage = false;

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

        // Same reasoning as TallStackQuotations::mount() — Client uses
        // App\Providers\AppServiceProvider::registerCompanyRoleGate()'s
        // Gate::before (governs create/update/delete for every
        // BelongsToCompany model), which reads the active Filament tenant
        // rather than a route parameter.
        app(Tenancy::class)->set($company);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Client::class);

        $this->resetForm();
        $this->showClientModal = true;
    }

    public function openEditModal(int $id): void
    {
        $client = $this->findScoped($id);

        if (! $client) {
            return;
        }

        $this->authorize('update', $client);

        $this->editingClientId = $client->id;
        $this->name = $client->name;
        $this->email = $client->email;
        $this->phone = $client->phone;
        $this->website = $client->website;
        $this->currency_code = $client->currency_code;
        $this->tax_number = $client->tax_number;
        $this->id_number = $client->id_number;
        $this->legacy_client_id = $client->legacy_client_id ? (string) $client->legacy_client_id : null;
        $this->default_discount = (float) $client->default_discount;
        $this->default_discount_is_percentage = (bool) $client->default_discount_is_percentage;
        $this->address_line_1 = $client->address_line_1;
        $this->address_line_2 = $client->address_line_2;
        $this->city = $client->city;
        $this->state = $client->state;
        $this->postal_code = $client->postal_code;
        $this->country_code = $client->country_code;
        $this->notes = $client->notes;
        $this->showClientModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'currency_code' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:255'],
            'legacy_client_id' => ['nullable', 'numeric'],
            'default_discount' => ['numeric', 'min:0'],
            'default_discount_is_percentage' => ['boolean'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:2'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = [
            ...$data,
            'legacy_client_id' => filled($data['legacy_client_id']) ? $data['legacy_client_id'] : null,
        ];

        if ($this->editingClientId) {
            $client = $this->findScoped($this->editingClientId);

            if (! $client) {
                return;
            }

            $this->authorize('update', $client);

            $client->update($payload);
            $this->toast()->success('Client saved.')->send();
        } else {
            $this->authorize('create', Client::class);

            $payload['company_id'] = $this->company->id;
            Client::create($payload);
            $this->toast()->success('Client created.')->send();
        }

        $this->showClientModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingClientId = null;
        $this->name = null;
        $this->email = null;
        $this->phone = null;
        $this->website = null;
        $this->currency_code = $this->company->currency_code;
        $this->tax_number = null;
        $this->id_number = null;
        $this->legacy_client_id = null;
        $this->default_discount = 0;
        $this->default_discount_is_percentage = false;
        $this->address_line_1 = null;
        $this->address_line_2 = null;
        $this->city = null;
        $this->state = null;
        $this->postal_code = null;
        $this->country_code = null;
        $this->notes = null;
    }

    /** Never trust a bare `Client::find()` — always re-check company ownership explicitly, same guard every other TALL-stack page uses. */
    private function findScoped(?int $id): ?Client
    {
        if (! $id) {
            return null;
        }

        $client = Client::find($id);

        if (! $client || $client->company_id !== $this->company->id) {
            return null;
        }

        return $client;
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        $clients = Client::query()
            ->where('company_id', $this->company->id)
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->orderBy('name')
            ->paginate(10)
            ->through(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email ?? '—',
                'phone' => $client->phone ?? '—',
                'currency_code' => $client->currency_code ?? '—',
                'balance' => (float) $client->balance,
                'balance_formatted' => Money::format((float) $client->balance, $client->currency_code ?: $currency),
                'paid_to_date_formatted' => Money::format((float) $client->paid_to_date, $client->currency_code ?: $currency),
                'tax_number' => $client->tax_number ?? '—',
            ]);

        return view('livewire.tallstack-clients', [
            'clients' => $clients,
            'currencies' => Currency::query()->orderBy('code')->pluck('code', 'code'),
        ])->layoutData([
            'company' => $this->company,
            'active' => 'clients',
            'title' => 'Clients',
        ]);
    }
}
