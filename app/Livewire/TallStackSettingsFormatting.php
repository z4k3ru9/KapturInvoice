<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack "Formatting" settings screen — consolidates the display/
 * locale-shaped Company fields that used to live on Company & Taxes'
 * own Identity card (`currency_code`, `timezone`) plus
 * `CompanySetting::default_document_language`, which had no Settings
 * field anywhere at all despite being read by every one of the 12
 * launch document types' own `resolveDocumentLanguage()` fallback chain
 * (see e.g. App\Models\Invoice's own copy of that method). Pulled out
 * into its own tab per the 2026-09-17 Settings reorganization — see
 * memory.md — rather than left scattered across two different pages for
 * two different models.
 *
 * Authorization: same "View settings: Owner/Admin ... Auditor never"
 * gate every other TALL-stack Settings page uses
 * (App\Enums\CompanyRole::settingsRoles(), CompanyPolicy::viewSettings()).
 */
#[Layout('components.tallstack.app')]
class TallStackSettingsFormatting extends Component
{
    use Interactions;

    public Company $company;

    public ?string $currency_code = null;

    public ?string $timezone = null;

    public ?string $default_document_language = null;

    public function mount(Company $company): void
    {
        $user = Auth::user();

        abort_unless($user && $user->canAccessTenant($company), 403);
        abort_unless($user->can('viewSettings', $company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);

        $this->currency_code = $company->currency_code;
        // NOT NULL column (default 'UTC') — see the identical fallback
        // in TallStackSettingsCompanyTaxes::mount() for why this can
        // never be left blank.
        $this->timezone = $company->timezone ?: 'UTC';

        $settings = CompanySetting::query()->firstOrCreate(['company_id' => $company->id]);
        $this->default_document_language = $settings->default_document_language ?: 'id';
    }

    public function save(): void
    {
        $this->authorize('viewSettings', $this->company);

        $data = $this->validate([
            'currency_code' => ['nullable', 'string', 'max:3', 'exists:currencies,code'],
            'timezone' => ['required', 'string', 'max:64', Rule::in(\DateTimeZone::listIdentifiers())],
            'default_document_language' => ['required', 'string', Rule::in(['id', 'en'])],
        ]);

        $this->company->update([
            'currency_code' => $data['currency_code'],
            'timezone' => $data['timezone'],
        ]);

        CompanySetting::query()->updateOrCreate(
            ['company_id' => $this->company->id],
            ['default_document_language' => $data['default_document_language']]
        );

        $this->toast()->success('Settings saved.')->send();
    }

    public function render(): View
    {
        return view('livewire.tallstack-settings-formatting', [
            'currencies' => Currency::query()->orderBy('code')->pluck('code', 'code'),
            'timezones' => collect(\DateTimeZone::listIdentifiers())->mapWithKeys(fn (string $tz) => [$tz => $tz]),
        ])
            ->layoutData([
                'company' => $this->company,
                'active' => 'settings',
                'title' => 'Formatting',
            ]);
    }
}
