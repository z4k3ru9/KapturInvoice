<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack "Client Portal" settings screen — see
 * App\Livewire\TallStackSettingsIdentity's docblock for the established
 * pattern this follows (presentation-layer swap only). Mirrors the
 * equivalent pre-TallStackUI Filament client portal settings page's single
 * `portal_enabled` field on App\Models\CompanySetting exactly — this admin
 * page only toggles behaviour, it never renders the portal itself
 * (App\Livewire\Portal\*).
 *
 * Authorization: same "View settings: Owner/Admin ... Auditor never" gate
 * (App\Enums\CompanyRole::settingsRoles(), CompanyPolicy::viewSettings())
 * every other TALL-stack Settings page uses, re-checked here in mount()
 * and again in save() since this route sits outside Filament's own panel
 * authorization.
 */
#[Layout('components.tallstack.app')]
class TallStackSettingsClientPortal extends Component
{
    use Interactions;

    public Company $company;

    public bool $portal_enabled = false;

    // Both were already real, actively-read CompanySetting columns
    // (view-invoice.blade.php:169/239 gate the Pay section and the
    // e-signature capture on these respectively) with no Settings field
    // anywhere — a settings-UI gap, not a missing-column one.
    public bool $portal_allow_client_payments = false;

    public bool $portal_require_signature = false;

    public function mount(Company $company): void
    {
        $user = Auth::user();

        abort_unless($user && $user->canAccessTenant($company), 403);
        abort_unless($user->can('viewSettings', $company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);

        $setting = CompanySetting::query()->firstOrCreate(['company_id' => $company->id]);
        $this->portal_enabled = (bool) $setting->portal_enabled;
        $this->portal_allow_client_payments = (bool) $setting->portal_allow_client_payments;
        $this->portal_require_signature = (bool) $setting->portal_require_signature;
    }

    public function save(): void
    {
        $this->authorize('viewSettings', $this->company);

        $data = $this->validate([
            'portal_enabled' => ['boolean'],
            'portal_allow_client_payments' => ['boolean'],
            'portal_require_signature' => ['boolean'],
        ]);

        CompanySetting::query()->updateOrCreate(
            ['company_id' => $this->company->id],
            $data
        );

        $this->toast()->success('Settings saved.')->send();
    }

    public function render(): View
    {
        return view('livewire.tallstack-settings-client-portal')
            ->layoutData([
                'company' => $this->company,
                'active' => 'settings',
                'title' => 'Client Portal',
            ]);
    }
}
