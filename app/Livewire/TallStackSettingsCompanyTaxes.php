<?php

namespace App\Livewire;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\CompanyTaxSetting;
use App\Services\PeriodLockService;
use App\Support\Tenancy\Tenancy;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;
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
    use Interactions;

    public Company $company;

    // --- Identity — EditCompanyProfile's own field set. -----------------
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
    // and every generated PDF's company header, but had no Settings field
    // of its own until now (address_line_1/2, city, state, postal_code,
    // country_code were already real #[Fillable] Company columns — this
    // was a settings-UI gap, not a missing-column one). Same field set/
    // validation as TallStackVendors's own address block. ------------------
    public ?string $address_line_1 = null;

    public ?string $address_line_2 = null;

    public ?string $city = null;

    public ?string $state = null;

    public ?string $postal_code = null;

    public ?string $country_code = null;

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

    // --- Period lock — App\Services\PeriodLockService was fully built
    // (close/reopen, role-gated reopen with a required audited reason)
    // but had no caller anywhere in the app until now; same
    // build-the-backend-first-wire-the-UI-later gap the Hold/Release-hold
    // mechanism had on Jobs. `periodLockedThrough` mirrors the current
    // stored value (null = no lock); `closeThroughDate`/`reopenReason`
    // are this form's own working inputs for the two actions. -----------
    public ?string $periodLockedThrough = null;

    public ?string $closeThroughDate = null;

    public bool $showReopenModal = false;

    public ?string $reopenReason = null;

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
        $this->is_active = (bool) $company->is_active;
        $this->address_line_1 = $company->address_line_1;
        $this->address_line_2 = $company->address_line_2;
        $this->city = $company->city;
        $this->state = $company->state;
        $this->postal_code = $company->postal_code;
        $this->country_code = $company->country_code;
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

        $this->periodLockedThrough = $company->settings?->period_locked_through?->toDateString();
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
            'code' => ['nullable', 'string', 'max:20'],
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
            'quote_prefix' => ['nullable', 'string', 'max:20'],
            'credit_prefix' => ['nullable', 'string', 'max:20'],
            'tax_enabled' => ['boolean'],
            'standard_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'dpp_factor_numerator' => ['nullable', 'integer', 'min:0', 'max:255'],
            'dpp_factor_denominator' => ['nullable', 'integer', 'min:0', 'max:255'],
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

        $this->toast()->success('Settings saved.')->send();
    }

    // --- Period lock ---------------------------------------------------------

    /** Closing (moving the lock forward) is not itself destructive, so any settings-capable role may do it — App\Services\PeriodLockService's own docblock. */
    public function closePeriod(): void
    {
        $this->authorize('viewSettings', $this->company);

        $data = $this->validate(['closeThroughDate' => ['required', 'date']]);

        app(PeriodLockService::class)->close($this->company, Carbon::parse($data['closeThroughDate']));

        $this->company->refresh();
        $this->periodLockedThrough = $this->company->settings?->period_locked_through?->toDateString();
        $this->closeThroughDate = null;

        $this->toast()->success('Period closed.', "Dates on or before {$this->periodLockedThrough} are now locked.")->send();
    }

    public function openReopenModal(): void
    {
        $this->authorize('viewSettings', $this->company);

        $this->reopenReason = null;
        $this->showReopenModal = true;
    }

    /** Reopening is restricted (Owner/Accountant, reason required, audited) — PeriodLockService::reopen() enforces the role check itself, this just surfaces its RuntimeException as a form error instead of a 500. */
    public function reopenPeriod(): void
    {
        $this->authorize('viewSettings', $this->company);

        $data = $this->validate(['reopenReason' => ['required', 'string', 'max:1000']]);

        try {
            app(PeriodLockService::class)->reopen($this->company, Auth::user(), $data['reopenReason']);
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not reopen period', $e->getMessage())->send();

            return;
        }

        $this->company->refresh();
        $this->periodLockedThrough = null;
        $this->showReopenModal = false;
        $this->reopenReason = null;

        $this->toast()->success('Period reopened.')->send();
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
        ])
            ->layoutData([
                'company' => $this->company,
                'active' => 'settings',
                'title' => 'Company & Taxes',
            ]);
    }
}
