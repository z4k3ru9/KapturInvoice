<?php

namespace App\Livewire;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack "Identity" settings screen — who this company is: name,
 * slug, domain, contact details, active status, and address. Split out
 * of the former "Company & Taxes" mega-page (2026-09-17 Settings
 * reorganization, see memory.md) alongside Formatting/Documents &
 * Numbering/Payment Method/Taxes — that one page had accreted seven
 * unrelated concerns over time; this is the "who" slice specifically.
 *
 * Authorization: "View settings: Owner/Admin ... Auditor never"
 * (docs/rebuild/Specs.md §10, App\Policies\CompanyPolicy::viewSettings(),
 * App\Enums\CompanyRole::settingsRoles()), re-checked here in mount() and
 * again in save() since this route sits outside Filament's own panel
 * authorization (there is no Filament panel anymore — TallStackUI).
 */
#[Layout('components.tallstack.app')]
class TallStackSettingsIdentity extends Component
{
    use Interactions;

    public Company $company;

    public ?string $name = null;

    public ?string $slug = null;

    public ?string $domain = null;

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $tax_number = null;

    // `is_active` gates login/portal access entirely (App\Models\User::
    // canAccessTenant() checks it unconditionally, even for a
    // super-admin) — Owner-only to change, confirmed client-side
    // (wire:confirm) since disabling the company you're currently
    // viewing locks you out of it immediately, not just eventually.
    public bool $is_active = true;

    // --- Address — printed on the public homepage's "Get in touch" strip
    // and every generated PDF's company header. -------------------------
    public ?string $address_line_1 = null;

    public ?string $address_line_2 = null;

    public ?string $city = null;

    public ?string $state = null;

    public ?string $postal_code = null;

    public ?string $country_code = null;

    // --- Preferences — a company-wide default rather than per-user, see
    // the dashboard_refresh_seconds column's own migration docblock. -----
    public ?int $dashboard_refresh_seconds = null;

    public function mount(Company $company): void
    {
        $user = Auth::user();

        abort_unless($user && $user->canAccessTenant($company), 403);
        abort_unless($user->can('viewSettings', $company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);

        $this->name = $company->name;
        $this->slug = $company->slug;
        $this->domain = $company->domain;
        $this->email = $company->email;
        $this->phone = $company->phone;
        $this->tax_number = $company->tax_number;
        $this->is_active = (bool) $company->is_active;
        $this->address_line_1 = $company->address_line_1;
        $this->address_line_2 = $company->address_line_2;
        $this->city = $company->city;
        $this->state = $company->state;
        $this->postal_code = $company->postal_code;
        $this->country_code = $company->country_code;
        $this->dashboard_refresh_seconds = $company->dashboard_refresh_seconds ?? 0;
    }

    public function save(): void
    {
        $this->authorize('viewSettings', $this->company);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('companies', 'slug')->ignore($this->company->id)],
            'domain' => ['nullable', 'string', 'max:255', Rule::unique('companies', 'domain')->ignore($this->company->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:2'],
            'dashboard_refresh_seconds' => ['nullable', 'integer', Rule::in([0, 30, 60, 120, 300])],
        ]);

        $payload = [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'domain' => $data['domain'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'tax_number' => $data['tax_number'],
            'address_line_1' => $data['address_line_1'],
            'address_line_2' => $data['address_line_2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'postal_code' => $data['postal_code'],
            'country_code' => $data['country_code'],
            'dashboard_refresh_seconds' => $data['dashboard_refresh_seconds'] ?: null,
        ];

        // `is_active` gates login/portal access unconditionally — Owner
        // only, and only actually written when it's genuinely changing
        // (never blindly re-writes the column every save).
        if ($data['is_active'] !== $this->company->is_active) {
            abort_unless(Auth::user()->hasCompanyRole($this->company, CompanyRole::Owner), 403);
            $payload['is_active'] = $data['is_active'];
        }

        $this->company->update($payload);
        $this->company->refresh();

        $this->toast()->success('Settings saved.')->send();
    }

    public function render(): View
    {
        return view('livewire.tallstack-settings-identity')
            ->layoutData([
                'company' => $this->company,
                'active' => 'settings',
                'title' => 'Identity',
            ]);
    }
}
