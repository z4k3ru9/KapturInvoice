<?php

namespace App\Livewire;

use App\Models\Company;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack "Branding" settings screen — mirrors
 * App\Filament\Pages\Settings\EditBrandingSettings field-for-field:
 * logo/primary_color/secondary_color (the same Company columns
 * App\Livewire\TallStackSettingsCompanyTaxes's own Branding section
 * edits — both forms save to the same row, per CLAUDE.md's documented
 * dual-entry-point pattern) plus the signatory/banking fields
 * (signatory_name/title/signature image, bank_name/account_number/
 * account_name, payment_instructions) added to that Filament page since
 * this app's original branding-settings docblock was written.
 */
#[Layout('components.tallstack.app')]
class TallStackSettingsBranding extends Component
{
    use Interactions, WithFileUploads;

    public Company $company;

    public ?string $primary_color = null;

    public ?string $secondary_color = null;

    public mixed $logo = null;

    public ?string $existingLogoPath = null;

    public ?string $existingLogoDataUri = null;

    public ?string $signatory_name = null;

    public ?string $signatory_title = null;

    public mixed $signature = null;

    public ?string $existingSignaturePath = null;

    public ?string $existingSignatureDataUri = null;

    public ?string $bank_name = null;

    public ?string $bank_account_number = null;

    public ?string $bank_account_name = null;

    public ?string $payment_instructions = null;

    public function mount(Company $company): void
    {
        $user = Auth::user();

        abort_unless($user && $user->canAccessTenant($company), 403);
        abort_unless($user->can('viewSettings', $company), 403);

        $this->company = $company;

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($company, isQuiet: true);

        $this->primary_color = $company->primary_color;
        $this->secondary_color = $company->secondary_color;
        $this->existingLogoPath = $company->logo_path;
        $this->existingLogoDataUri = $company->getLogoDataUri();
        $this->signatory_name = $company->signatory_name;
        $this->signatory_title = $company->signatory_title;
        $this->existingSignaturePath = $company->signature_image_path;
        $this->existingSignatureDataUri = $company->getSignatureDataUri();
        $this->bank_name = $company->bank_name;
        $this->bank_account_number = $company->bank_account_number;
        $this->bank_account_name = $company->bank_account_name;
        $this->payment_instructions = $company->payment_instructions;
    }

    public function removeLogo(): void
    {
        $this->logo = null;
        $this->existingLogoPath = null;
        $this->existingLogoDataUri = null;
    }

    public function removeSignature(): void
    {
        $this->signature = null;
        $this->existingSignaturePath = null;
        $this->existingSignatureDataUri = null;
    }

    public function save(): void
    {
        $this->authorize('viewSettings', $this->company);

        $data = $this->validate([
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
            'signature' => ['nullable', 'image', 'max:2048'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'payment_instructions' => ['nullable', 'string'],
        ]);

        $payload = [
            'primary_color' => $data['primary_color'],
            'secondary_color' => $data['secondary_color'],
            'signatory_name' => $data['signatory_name'],
            'signatory_title' => $data['signatory_title'],
            'bank_name' => $data['bank_name'],
            'bank_account_number' => $data['bank_account_number'],
            'bank_account_name' => $data['bank_account_name'],
            'payment_instructions' => $data['payment_instructions'],
            'logo_path' => $this->existingLogoPath,
            'signature_image_path' => $this->existingSignaturePath,
        ];

        if ($this->logo) {
            $payload['logo_path'] = $this->logo->store('logos', config('filesystems.default'));
        }

        if ($this->signature) {
            $payload['signature_image_path'] = $this->signature->store('signatures', config('filesystems.default'));
        }

        $this->company->update($payload);
        $this->company->refresh();

        $this->existingLogoPath = $this->company->logo_path;
        $this->existingLogoDataUri = $this->company->getLogoDataUri();
        $this->existingSignaturePath = $this->company->signature_image_path;
        $this->existingSignatureDataUri = $this->company->getSignatureDataUri();
        $this->logo = null;
        $this->signature = null;

        $this->toast()->success('Branding saved.')->send();
    }

    public function render(): View
    {
        return view('livewire.tallstack-settings-branding')
            ->layoutData([
                'company' => $this->company,
                'active' => 'settings-branding',
                'title' => 'Branding',
            ]);
    }
}
