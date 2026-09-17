<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\TaxRate;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack "Documents & Numbering" settings screen — everything
 * about how documents get numbered, in one place. Previously split
 * across two pages for no functional reason (the company code/prefixes
 * lived on the former "Company & Taxes" mega-page while the next-number
 * counters and document defaults lived here) — merged in the 2026-09-17
 * Settings reorganization (see memory.md). `invoice_prefix`/
 * `quote_prefix`/`credit_prefix` are edited here but, worth knowing,
 * `App\Services\DocumentNumberGenerator::next()` never actually reads
 * them — it builds numbers from `company->code` plus a hardcoded
 * document-type constant instead (see docs/out-of-scope-findings.md).
 *
 * Authorization: same "View settings: Owner/Admin ... Auditor never" gate
 * (App\Enums\CompanyRole::settingsRoles(), CompanyPolicy::viewSettings())
 * every other TALL-stack Settings page uses, re-checked here in mount()
 * and again in save() since this route sits outside Filament's own panel
 * authorization.
 *
 * `default_expire_after_days` (repair plan Phase 11 / decision gate G5):
 * prefills a new Quotation's `valid_until` (today + N days) on
 * TallStackQuotationForm::mount().
 */
#[Layout('components.tallstack.app')]
class TallStackSettingsDocumentsNumbering extends Component
{
    use Interactions;

    public Company $company;

    // --- Document code/prefixes — moved in from the former Company &
    // Taxes page. --------------------------------------------------------
    public ?string $code = null;

    public ?string $invoice_prefix = null;

    public ?string $quote_prefix = null;

    public ?string $credit_prefix = null;

    public bool $codesLocked = false;

    // --- Next-number counters and document-creation defaults ------------
    public ?int $invoice_next_number = null;

    public ?int $quote_next_number = null;

    public ?int $credit_next_number = null;

    public ?string $default_payment_terms = null;

    public ?int $default_tax_rate_1_id = null;

    public ?int $default_tax_rate_2_id = null;

    public ?int $default_expire_after_days = null;

    public function mount(Company $company): void
    {
        $user = Auth::user();

        abort_unless($user && $user->canAccessTenant($company), 403);
        abort_unless($user->can('viewSettings', $company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);

        $this->code = $company->code;
        $this->invoice_prefix = $company->invoice_prefix;
        $this->quote_prefix = $company->quote_prefix;
        $this->credit_prefix = $company->credit_prefix;
        $this->codesLocked = filled($company->codes_locked_at);
        $this->invoice_next_number = $company->invoice_next_number;
        $this->quote_next_number = $company->quote_next_number;
        $this->credit_next_number = $company->credit_next_number;
        $this->default_payment_terms = $company->default_payment_terms;
        $this->default_tax_rate_1_id = $company->default_tax_rate_1_id;
        $this->default_tax_rate_2_id = $company->default_tax_rate_2_id;
        $this->default_expire_after_days = $company->default_expire_after_days;
    }

    /** @return array<int, array{label: string, value: string}> */
    public function getTaxRateOptionsProperty(): array
    {
        return TaxRate::query()
            ->where('company_id', $this->company->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (TaxRate $taxRate) => [
                'label' => $taxRate->name,
                'value' => (string) $taxRate->id,
            ])->all();
    }

    public function save(): void
    {
        $this->authorize('viewSettings', $this->company);

        $data = $this->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
            'quote_prefix' => ['nullable', 'string', 'max:20'],
            'credit_prefix' => ['nullable', 'string', 'max:20'],
            'invoice_next_number' => ['required', 'integer', 'min:1'],
            'quote_next_number' => ['required', 'integer', 'min:1'],
            'credit_next_number' => ['required', 'integer', 'min:1'],
            'default_payment_terms' => ['nullable', 'string'],
            'default_tax_rate_1_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'default_tax_rate_2_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'default_expire_after_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $payload = [
            'invoice_prefix' => $data['invoice_prefix'],
            'quote_prefix' => $data['quote_prefix'],
            'credit_prefix' => $data['credit_prefix'],
            'invoice_next_number' => $data['invoice_next_number'],
            'quote_next_number' => $data['quote_next_number'],
            'credit_next_number' => $data['credit_next_number'],
            'default_payment_terms' => $data['default_payment_terms'],
            'default_tax_rate_1_id' => $data['default_tax_rate_1_id'],
            'default_tax_rate_2_id' => $data['default_tax_rate_2_id'],
            'default_expire_after_days' => $data['default_expire_after_days'],
        ];

        // "Locks after the first document is issued" — a disabled field
        // never round-trips through Livewire's wire:model anyway, but
        // this makes the same rule explicit server-side too.
        if (! $this->codesLocked) {
            $payload['code'] = $data['code'];
        }

        $this->company->update($payload);
        $this->company->refresh();

        $this->toast()->success('Settings saved.')->send();
    }

    public function render(): View
    {
        return view('livewire.tallstack-settings-documents-numbering')
            ->layoutData([
                'company' => $this->company,
                'active' => 'settings',
                'title' => 'Documents & Numbering',
            ]);
    }
}
