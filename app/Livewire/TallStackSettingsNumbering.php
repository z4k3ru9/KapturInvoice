<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\TaxRate;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack "Numbering" settings screen — see
 * App\Livewire\TallStackSettingsCompanyTaxes's docblock for the established
 * pattern this follows (presentation-layer swap only). Mirrors
 * App\Filament\Pages\Settings\EditNumberingSettings field-for-field, minus
 * invoice_prefix/quote_prefix/credit_prefix, which already live on the
 * TallStack Company & Taxes page — only the fields not already built
 * anywhere in TallStackUI are duplicated here: the three `_next_number`
 * live sequence counters (App\Services\DocumentNumberGenerator's source of
 * truth for the very next document's number), default_payment_terms, and
 * default_tax_rate_1_id/default_tax_rate_2_id.
 *
 * Authorization: same "View settings: Owner/Admin ... Auditor never" gate
 * (App\Enums\CompanyRole::settingsRoles(), CompanyPolicy::viewSettings())
 * every other TALL-stack Settings page uses, re-checked here in mount()
 * and again in save() since this route sits outside Filament's own panel
 * authorization.
 */
#[Layout('components.tallstack.app')]
class TallStackSettingsNumbering extends Component
{
    use Interactions;

    public Company $company;

    public ?int $invoice_next_number = null;

    public ?int $quote_next_number = null;

    public ?int $credit_next_number = null;

    public ?string $default_payment_terms = null;

    public ?int $default_tax_rate_1_id = null;

    public ?int $default_tax_rate_2_id = null;

    public function mount(Company $company): void
    {
        $user = Auth::user();

        abort_unless($user && $user->canAccessTenant($company), 403);
        abort_unless($user->can('viewSettings', $company), 403);

        $this->company = $company;

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company, isQuiet: true);

        $this->invoice_next_number = $company->invoice_next_number;
        $this->quote_next_number = $company->quote_next_number;
        $this->credit_next_number = $company->credit_next_number;
        $this->default_payment_terms = $company->default_payment_terms;
        $this->default_tax_rate_1_id = $company->default_tax_rate_1_id;
        $this->default_tax_rate_2_id = $company->default_tax_rate_2_id;
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
            'invoice_next_number' => ['required', 'integer', 'min:1'],
            'quote_next_number' => ['required', 'integer', 'min:1'],
            'credit_next_number' => ['required', 'integer', 'min:1'],
            'default_payment_terms' => ['nullable', 'string'],
            'default_tax_rate_1_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
            'default_tax_rate_2_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
        ]);

        $this->company->update($data);

        $this->company->refresh();

        $this->toast()->success('Settings saved.')->send();
    }

    public function render(): View
    {
        return view('livewire.tallstack-settings-numbering')
            ->layoutData([
                'company' => $this->company,
                'active' => 'settings-numbering',
                'title' => 'Numbering',
            ]);
    }
}
