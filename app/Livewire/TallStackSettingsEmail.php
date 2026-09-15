<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\CompanySetting;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack "Email & Reminders" settings screen — mirrors
 * App\Filament\Pages\Settings\EditEmailSettings field-for-field: every
 * invoice/quote/quotation/payment subject+body template, the reminder1-4
 * enabled/days/direction/field config, and the late-fee tiers. See
 * App\Livewire\TallStackSettingsCompanyTaxes's docblock for the shared
 * authorization/presentation-layer-swap reasoning this and every other
 * Settings page in this phase follows.
 */
#[Layout('components.tallstack.app')]
class TallStackSettingsEmail extends Component
{
    use Interactions;

    public Company $company;

    public ?string $invoice_email_subject = null;

    public ?string $invoice_email_body = null;

    public ?string $quote_email_subject = null;

    public ?string $quote_email_body = null;

    public ?string $quotation_email_subject = null;

    public ?string $quotation_email_body = null;

    public ?string $payment_email_subject = null;

    public ?string $payment_email_body = null;

    /** @var array<int, array{enabled: bool, days: ?int, direction: string, field: string}> */
    public array $reminders = [];

    /** @var array<int, array{amount: ?float, percent: ?float}> */
    public array $lateFees = [];

    public function mount(Company $company): void
    {
        $user = Auth::user();

        abort_unless($user && $user->canAccessTenant($company), 403);
        abort_unless($user->can('viewSettings', $company), 403);

        $this->company = $company;

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company, isQuiet: true);

        $settings = CompanySetting::query()->firstOrCreate(['company_id' => $company->id]);

        $this->invoice_email_subject = $settings->invoice_email_subject;
        $this->invoice_email_body = $settings->invoice_email_body;
        $this->quote_email_subject = $settings->quote_email_subject;
        $this->quote_email_body = $settings->quote_email_body;
        $this->quotation_email_subject = $settings->quotation_email_subject;
        $this->quotation_email_body = $settings->quotation_email_body;
        $this->payment_email_subject = $settings->payment_email_subject;
        $this->payment_email_body = $settings->payment_email_body;

        foreach (range(1, 4) as $n) {
            $this->reminders[$n] = [
                'enabled' => (bool) $settings->{"reminder{$n}_enabled"},
                'days' => $settings->{"reminder{$n}_days"},
                'direction' => $settings->{"reminder{$n}_direction"} ?? 'after',
                'field' => $settings->{"reminder{$n}_field"} ?? 'due_date',
            ];
        }

        foreach (range(1, 3) as $n) {
            $this->lateFees[$n] = [
                'amount' => $settings->{"late_fee{$n}_amount"} !== null ? (float) $settings->{"late_fee{$n}_amount"} : null,
                'percent' => $settings->{"late_fee{$n}_percent"} !== null ? (float) $settings->{"late_fee{$n}_percent"} : null,
            ];
        }
    }

    public function save(): void
    {
        $this->authorize('viewSettings', $this->company);

        $data = $this->validate([
            'invoice_email_subject' => ['nullable', 'string', 'max:255'],
            'invoice_email_body' => ['nullable', 'string'],
            'quote_email_subject' => ['nullable', 'string', 'max:255'],
            'quote_email_body' => ['nullable', 'string'],
            'quotation_email_subject' => ['nullable', 'string', 'max:255'],
            'quotation_email_body' => ['nullable', 'string'],
            'payment_email_subject' => ['nullable', 'string', 'max:255'],
            'payment_email_body' => ['nullable', 'string'],
            'reminders.*.enabled' => ['boolean'],
            'reminders.*.days' => ['nullable', 'integer', 'min:0'],
            'reminders.*.direction' => ['required', 'in:before,after'],
            'reminders.*.field' => ['required', 'in:due_date,invoice_date'],
            'lateFees.*.amount' => ['nullable', 'numeric', 'min:0'],
            'lateFees.*.percent' => ['nullable', 'numeric', 'min:0'],
        ]);

        $payload = [
            'invoice_email_subject' => $data['invoice_email_subject'],
            'invoice_email_body' => $data['invoice_email_body'],
            'quote_email_subject' => $data['quote_email_subject'],
            'quote_email_body' => $data['quote_email_body'],
            'quotation_email_subject' => $data['quotation_email_subject'],
            'quotation_email_body' => $data['quotation_email_body'],
            'payment_email_subject' => $data['payment_email_subject'],
            'payment_email_body' => $data['payment_email_body'],
        ];

        foreach (range(1, 4) as $n) {
            $payload["reminder{$n}_enabled"] = $data['reminders'][$n]['enabled'];
            $payload["reminder{$n}_days"] = $data['reminders'][$n]['days'];
            $payload["reminder{$n}_direction"] = $data['reminders'][$n]['direction'];
            $payload["reminder{$n}_field"] = $data['reminders'][$n]['field'];
        }

        foreach (range(1, 3) as $n) {
            $payload["late_fee{$n}_amount"] = $data['lateFees'][$n]['amount'];
            $payload["late_fee{$n}_percent"] = $data['lateFees'][$n]['percent'];
        }

        CompanySetting::query()->updateOrCreate(['company_id' => $this->company->id], $payload);

        $this->toast()->success('Settings saved.')->send();
    }

    public function render(): View
    {
        return view('livewire.tallstack-settings-email')
            ->layoutData([
                'company' => $this->company,
                'active' => 'settings-email',
                'title' => 'Email & Reminders',
            ]);
    }
}
