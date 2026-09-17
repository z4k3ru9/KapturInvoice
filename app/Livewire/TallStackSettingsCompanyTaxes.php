<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\CompanyTaxSetting;
use App\Models\Currency;
use App\Support\Tenancy\Tenancy;
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
 * this follows (presentation-layer swap only). Mirrors the equivalent
 * pre-TallStackUI Filament company-profile page's "Identity"/"Branding"/
 * "Document numbering" sections field-for-field, plus
 * App\Models\CompanyTaxSetting's tax_enabled/standard_tax_rate/
 * dpp_factor_numerator/dpp_factor_denominator fields — no Filament page
 * ever edited CompanyTaxSetting, so this is the first UI (admin or
 * TALL-stack) that does.
 *
 * The fetched Stitch mockup ("Company & Taxes Settings — Company B
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

    // --- Dashboard — replaces the Dashboard's own manual refresh button
    // with a company-wide auto-refresh cadence, set here rather than per
    // user (see the new dashboard_refresh_seconds column's own migration
    // docblock for why). null/0 = off. ------------------------------------
    public ?int $dashboard_refresh_seconds = null;

    // --- Taxes — App\Models\CompanyTaxSetting's own field set. -----------
    public bool $tax_enabled = false;

    public ?float $standard_tax_rate = null;

    public ?int $dpp_factor_numerator = null;

    public ?int $dpp_factor_denominator = null;

    // --- Bank accounts — printed as a "Payment Method" section on the
    // Invoice PDF. A company may have more than one (e.g. two different
    // banks); a dedicated list/modal here rather than folded into the
    // main save() form above, matching TallStackSettingsLookups's own
    // pattern for a company-scoped repeatable list on a settings page. ---
    public bool $showBankAccountModal = false;

    public ?int $editingBankAccountId = null;

    public ?string $ba_bank_name = null;

    public ?string $ba_account_name = null;

    public ?string $ba_account_number = null;

    public ?string $ba_branch = null;

    public ?string $ba_swift_code = null;

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
        $this->dashboard_refresh_seconds = $company->dashboard_refresh_seconds ?? 0;

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
            'currency_code' => ['nullable', 'string', 'max:3', 'exists:currencies,code'],
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
            'dashboard_refresh_seconds' => ['nullable', 'integer', Rule::in([0, 30, 60, 120, 300])],
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
            'dashboard_refresh_seconds' => $data['dashboard_refresh_seconds'] ?: null,
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

    // --- Bank accounts -----------------------------------------------------

    public function openCreateBankAccountModal(): void
    {
        $this->authorize('viewSettings', $this->company);

        $this->resetBankAccountForm();
        $this->showBankAccountModal = true;
    }

    public function openEditBankAccountModal(int $id): void
    {
        $account = $this->scopedBankAccount($id);

        if (! $account) {
            return;
        }

        $this->authorize('viewSettings', $this->company);

        $this->editingBankAccountId = $account->id;
        $this->ba_bank_name = $account->bank_name;
        $this->ba_account_name = $account->account_name;
        $this->ba_account_number = $account->account_number;
        $this->ba_branch = $account->branch;
        $this->ba_swift_code = $account->swift_code;
        $this->showBankAccountModal = true;
    }

    public function saveBankAccount(): void
    {
        $this->authorize('viewSettings', $this->company);

        $data = $this->validate([
            'ba_bank_name' => ['required', 'string', 'max:255'],
            'ba_account_name' => ['required', 'string', 'max:255'],
            'ba_account_number' => ['required', 'string', 'max:255'],
            'ba_branch' => ['nullable', 'string', 'max:255'],
            'ba_swift_code' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = [
            'bank_name' => $data['ba_bank_name'],
            'account_name' => $data['ba_account_name'],
            'account_number' => $data['ba_account_number'],
            'branch' => $data['ba_branch'],
            'swift_code' => $data['ba_swift_code'],
        ];

        if ($this->editingBankAccountId) {
            $account = $this->scopedBankAccount($this->editingBankAccountId);

            if (! $account) {
                return;
            }

            $account->update($payload);
        } else {
            $payload['company_id'] = $this->company->id;
            $payload['sort_order'] = $this->company->bankAccounts()->count();
            CompanyBankAccount::create($payload);
        }

        $this->showBankAccountModal = false;
        $this->resetBankAccountForm();
        $this->toast()->success('Bank account saved.')->send();
    }

    public function deleteBankAccount(int $id): void
    {
        $account = $this->scopedBankAccount($id);

        if (! $account) {
            return;
        }

        $this->authorize('viewSettings', $this->company);

        $account->delete();

        $this->toast()->success('Bank account removed.')->send();
    }

    /** Never trust a bare `CompanyBankAccount::find()` — always re-check company ownership. */
    private function scopedBankAccount(?int $id): ?CompanyBankAccount
    {
        if (! $id) {
            return null;
        }

        $account = CompanyBankAccount::find($id);

        if (! $account || $account->company_id !== $this->company->id) {
            return null;
        }

        return $account;
    }

    private function resetBankAccountForm(): void
    {
        $this->editingBankAccountId = null;
        $this->ba_bank_name = null;
        $this->ba_account_name = null;
        $this->ba_account_number = null;
        $this->ba_branch = null;
        $this->ba_swift_code = null;
    }

    public function render(): View
    {
        $bankAccounts = $this->company->bankAccounts->map(fn (CompanyBankAccount $account) => [
            'id' => $account->id,
            'name' => "{$account->bank_name} — {$account->account_number}",
            'caption' => $account->branch
                ? "{$account->account_name} · {$account->branch}"
                : $account->account_name,
        ]);

        return view('livewire.tallstack-settings-company-taxes', [
            'bankAccounts' => $bankAccounts,
            'currencies' => Currency::query()->orderBy('code')->pluck('code', 'code'),
        ])
            ->layoutData([
                'company' => $this->company,
                'active' => 'settings',
                'title' => 'Company & Taxes',
            ]);
    }
}
