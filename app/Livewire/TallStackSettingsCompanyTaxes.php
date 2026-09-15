<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\CompanyTaxSetting;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack "Company & Taxes" settings screen — see
 * App\Livewire\TallStackQuotations's docblock for the established pattern
 * this follows (presentation-layer swap only). Mirrors
 * App\Filament\Pages\Tenancy\EditCompanyProfile's "Identity"/"Branding"/
 * "Document numbering" sections field-for-field, plus
 * App\Models\CompanyTaxSetting's tax_enabled/standard_tax_rate/
 * dpp_factor_numerator/dpp_factor_denominator fields — no Filament page
 * edits CompanyTaxSetting today, so this is the first UI (admin or
 * TALL-stack) that does.
 *
 * The fetched Stitch mockup ("Company & Taxes Settings — Axen Technology
 * Variant") sketches a much larger surface (DJP e-Faktur gateway/digital
 * certificate infrastructure, PPh 23/PP 55 withholding rates, NSFP quota
 * tracking, corporate banking virtual accounts/webhooks) that has no
 * backing column, model, or action anywhere in this app — those are
 * exactly the "deferred capabilities" CLAUDE.md's guard list rules out
 * (online payment gateway/reconciliation, a fuller tax-compliance engine).
 * This page borrows the mockup's card-grid visual language only; every
 * field on it maps to a real Company/CompanyTaxSetting column, nothing
 * invented.
 *
 * Authorization: "View settings: Owner/Admin ... Auditor never"
 * (docs/rebuild/Specs.md §10, App\Policies\CompanyPolicy::viewSettings(),
 * App\Enums\CompanyRole::settingsRoles()) — the exact same gate
 * EditCompanyProfile/EditEmailSettings/EditBrandingSettings already use,
 * re-checked here in mount() and again in save() since this route sits
 * outside Filament's own panel authorization.
 */
#[Layout('components.tallstack.app')]
class TallStackSettingsCompanyTaxes extends Component
{
    use Interactions, WithFileUploads;

    public Company $company;

    // --- Identity — EditCompanyProfile's own field set. -----------------
    public ?string $name = null;

    public ?string $slug = null;

    public ?string $domain = null;

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $tax_number = null;

    public ?string $currency_code = null;

    // --- Branding (also editable from the dedicated Branding page). -----
    public ?string $primary_color = null;

    public ?string $secondary_color = null;

    public mixed $logo = null;

    public ?string $existingLogoPath = null;

    public ?string $existingLogoDataUri = null;

    // --- Document numbering ----------------------------------------------
    public ?string $code = null;

    public ?string $invoice_prefix = null;

    public ?string $quote_prefix = null;

    public ?string $credit_prefix = null;

    public bool $codesLocked = false;

    // --- Taxes — App\Models\CompanyTaxSetting's own field set. -----------
    public bool $tax_enabled = false;

    public ?float $standard_tax_rate = null;

    public ?int $dpp_factor_numerator = null;

    public ?int $dpp_factor_denominator = null;

    public function mount(Company $company): void
    {
        $user = Auth::user();

        abort_unless($user && $user->canAccessTenant($company), 403);
        abort_unless($user->can('viewSettings', $company), 403);

        $this->company = $company;

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company, isQuiet: true);

        $this->name = $company->name;
        $this->slug = $company->slug;
        $this->domain = $company->domain;
        $this->email = $company->email;
        $this->phone = $company->phone;
        $this->tax_number = $company->tax_number;
        $this->currency_code = $company->currency_code;
        $this->primary_color = $company->primary_color;
        $this->secondary_color = $company->secondary_color;
        $this->existingLogoPath = $company->logo_path;
        $this->existingLogoDataUri = $company->getLogoDataUri();
        $this->code = $company->code;
        $this->invoice_prefix = $company->invoice_prefix;
        $this->quote_prefix = $company->quote_prefix;
        $this->credit_prefix = $company->credit_prefix;
        $this->codesLocked = filled($company->codes_locked_at);

        $taxSetting = CompanyTaxSetting::query()->firstOrCreate(['company_id' => $company->id]);
        $this->tax_enabled = (bool) $taxSetting->tax_enabled;
        $this->standard_tax_rate = $taxSetting->standard_tax_rate !== null ? (float) $taxSetting->standard_tax_rate : null;
        $this->dpp_factor_numerator = $taxSetting->dpp_factor_numerator;
        $this->dpp_factor_denominator = $taxSetting->dpp_factor_denominator;
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
            'currency_code' => ['nullable', 'string', 'max:3'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'code' => ['nullable', 'string', 'max:20'],
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
            'quote_prefix' => ['nullable', 'string', 'max:20'],
            'credit_prefix' => ['nullable', 'string', 'max:20'],
            'tax_enabled' => ['boolean'],
            'standard_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'dpp_factor_numerator' => ['nullable', 'integer', 'min:0', 'max:255'],
            'dpp_factor_denominator' => ['nullable', 'integer', 'min:0', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $payload = [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'domain' => $data['domain'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'tax_number' => $data['tax_number'],
            'currency_code' => $data['currency_code'],
            'primary_color' => $data['primary_color'],
            'secondary_color' => $data['secondary_color'],
        ];

        // "Locks after the first document is issued" — same guard
        // EditCompanyProfile's own TextInput::disabled() expresses; a
        // disabled field never round-trips through Livewire's wire:model
        // anyway, but this makes the same rule explicit server-side too.
        if (! $this->codesLocked) {
            $payload['code'] = $data['code'];
        }

        $payload['invoice_prefix'] = $data['invoice_prefix'];
        $payload['quote_prefix'] = $data['quote_prefix'];
        $payload['credit_prefix'] = $data['credit_prefix'];

        if ($this->logo) {
            $payload['logo_path'] = $this->logo->store('logos', config('filesystems.default'));
        }

        $this->company->update($payload);

        CompanyTaxSetting::query()->updateOrCreate(
            ['company_id' => $this->company->id],
            [
                'tax_enabled' => $data['tax_enabled'],
                'standard_tax_rate' => $data['standard_tax_rate'],
                'dpp_factor_numerator' => $data['dpp_factor_numerator'],
                'dpp_factor_denominator' => $data['dpp_factor_denominator'],
            ]
        );

        $this->company->refresh();
        $this->existingLogoPath = $this->company->logo_path;
        $this->existingLogoDataUri = $this->company->getLogoDataUri();
        $this->logo = null;

        $this->toast()->success('Settings saved.')->send();
    }

    public function render(): View
    {
        return view('livewire.tallstack-settings-company-taxes')
            ->layoutData([
                'company' => $this->company,
                'active' => 'settings-company-taxes',
                'title' => 'Company & Taxes',
            ]);
    }
}
