<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Settings\EditClientPortalSettings;
use App\Filament\Pages\Settings\EditEmailSettings;
use App\Filament\Pages\Settings\EditNumberingSettings;
use App\Filament\Resources\PaymentGateways\PaymentGatewayResource;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The four Settings pages/resource from docs/filament-admin-layout-design.md
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
            ->set('data.portal_require_signature', true)
            ->call('save');

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $this->company->id,
            'portal_require_signature' => true,
        ]);
    }

    public function test_payment_gateway_resource_index_page_renders(): void
    {
        // No dedicated Create page anymore — it opens via a modal instead,
        // covered by ModalCreateEditTest. See
        // docs/filament-admin-layout-design.md §6.
        $this->get(PaymentGatewayResource::getUrl('index', tenant: $this->company))->assertOk();
    }
}
