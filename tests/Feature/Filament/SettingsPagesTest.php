<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Settings\EditBrandingSettings;
use App\Filament\Pages\Settings\EditClientPortalSettings;
use App\Filament\Pages\Settings\EditEmailSettings;
use App\Filament\Pages\Settings\EditNumberingSettings;
use App\Filament\Resources\PaymentGateways\PaymentGatewayResource;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The five Settings pages/resource from docs/filament-admin-layout-design.md
 * §3 — each is a singleton per company, loaded/saved via
 * App\Filament\Pages\Settings\Concerns\InteractsWithSettingsRecord.
 */
class SettingsPagesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    public function test_numbering_settings_page_renders_and_saves(): void
    {
        $this->get(EditNumberingSettings::getUrl(tenant: $this->company))->assertOk();

        Livewire::test(EditNumberingSettings::class)
            ->set('data.invoice_prefix', 'INV-')
            ->set('data.invoice_next_number', 42)
            ->call('save');

        $this->assertSame('INV-', $this->company->fresh()->invoice_prefix);
        $this->assertSame(42, $this->company->fresh()->invoice_next_number);
    }

    public function test_email_settings_page_renders_and_saves(): void
    {
        $this->get(EditEmailSettings::getUrl(tenant: $this->company))->assertOk();

        Livewire::test(EditEmailSettings::class)
            ->set('data.reminder1_enabled', true)
            ->set('data.reminder1_days', 7)
            ->call('save');

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $this->company->id,
            'reminder1_enabled' => true,
            'reminder1_days' => 7,
        ]);
    }

    public function test_client_portal_settings_page_renders_and_saves(): void
    {
        $this->get(EditClientPortalSettings::getUrl(tenant: $this->company))->assertOk();

        Livewire::test(EditClientPortalSettings::class)
            ->set('data.portal_enabled', false)
            ->call('save');

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $this->company->id,
            'portal_enabled' => false,
        ]);
    }

    public function test_branding_settings_page_renders_and_saves(): void
    {
        Storage::fake(config('filesystems.default'));

        $this->get(EditBrandingSettings::getUrl(tenant: $this->company))->assertOk();

        Livewire::test(EditBrandingSettings::class)
            ->set('data.logo_path', UploadedFile::fake()->image('logo.png'))
            ->set('data.primary_color', '#112233')
            ->call('save');

        $company = $this->company->fresh();
        $this->assertSame('#112233', $company->primary_color);
        $this->assertNotNull($company->logo_path);
        Storage::disk(config('filesystems.default'))->assertExists($company->logo_path);
    }

    public function test_branding_settings_page_saves_signatory_and_banking_fields(): void
    {
        Storage::fake(config('filesystems.default'));

        Livewire::test(EditBrandingSettings::class)
            ->set('data.signatory_name', 'Jane Doe')
            ->set('data.signatory_title', 'Finance Director')
            ->set('data.signature_image_path', UploadedFile::fake()->image('signature.png'))
            ->set('data.bank_name', 'Bank Central Asia')
            ->set('data.bank_account_number', '1234567890')
            ->set('data.bank_account_name', 'Acme Pte Ltd')
            ->set('data.payment_instructions', 'Transfer to the account above and email proof of payment.')
            ->call('save');

        $company = $this->company->fresh();

        $this->assertSame('Jane Doe', $company->signatory_name);
        $this->assertSame('Finance Director', $company->signatory_title);
        $this->assertSame('Bank Central Asia', $company->bank_name);
        $this->assertSame('1234567890', $company->bank_account_number);
        $this->assertSame('Acme Pte Ltd', $company->bank_account_name);
        $this->assertSame('Transfer to the account above and email proof of payment.', $company->payment_instructions);
        $this->assertNotNull($company->signature_image_path);
        Storage::disk(config('filesystems.default'))->assertExists($company->signature_image_path);

        $this->assertNotNull($company->getSignatureDataUri());
        $this->assertStringStartsWith('data:image/png;base64,', $company->getSignatureDataUri());
    }

    public function test_get_signature_data_uri_is_null_when_unset(): void
    {
        $this->assertNull($this->company->getSignatureDataUri());
    }

    public function test_payment_gateway_resource_index_page_renders(): void
    {
        // No dedicated Create page anymore — it opens via a modal instead,
        // covered by ModalCreateEditTest. See
        // docs/filament-admin-layout-design.md §6.
        $this->get(PaymentGatewayResource::getUrl('index', tenant: $this->company))->assertOk();
    }
}
