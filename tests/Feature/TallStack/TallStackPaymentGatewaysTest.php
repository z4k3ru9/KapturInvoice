<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackPaymentGateways;
use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the TALL-stack Payment Gateways page (App\Livewire\TallStackPaymentGateways)
 * — a pre-Filament-removal audit gap (docs/rebuild/outputs/27-filament-parity-gap-prompts.md
 * prompt 20), built as a pure UI swap over the existing
 * App\Services\PaymentGateways\PaymentGatewayManager/App\Models\PaymentGateway
 * unmodified. See App\Livewire\TallStackReports's own test for the
 * established authorization-assertion shape this follows.
 */
class TallStackPaymentGatewaysTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->owner, ['role' => 'owner']);

        $this->actingAs($this->owner);
    }

    public function test_mount_aborts_for_a_user_without_access_to_the_company(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);

        Livewire::test(TallStackPaymentGateways::class, ['company' => $otherCompany])
            ->assertStatus(403);
    }

    public function test_list_shows_only_gateways_belonging_to_the_current_company(): void
    {
        PaymentGateway::create([
            'company_id' => $this->company->id,
            'name' => 'Local API (Indonesia)',
            'driver' => 'local_api',
            'is_enabled' => true,
            'config' => [],
            'accepted_credit_cards' => ['visa'],
        ]);

        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co', 'currency_code' => 'USD']);
        PaymentGateway::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Company Gateway',
            'driver' => 'manual',
            'is_enabled' => true,
            'config' => [],
        ]);

        Livewire::test(TallStackPaymentGateways::class, ['company' => $this->company])
            ->assertSee('Local API (Indonesia)')
            ->assertDontSee('Other Company Gateway');
    }

    public function test_owner_can_create_a_local_api_gateway(): void
    {
        Livewire::test(TallStackPaymentGateways::class, ['company' => $this->company])
            ->call('create')
            ->set('name', 'Midtrans Snap Production')
            ->set('driver', 'local_api')
            ->set('configBaseUrl', 'https://api.midtrans.com')
            ->set('configApiKey', 'secret-key')
            ->set('configMerchantId', 'M-001')
            ->set('configMethods', ['qris', 'virtual_account'])
            ->set('accepted_credit_cards', ['visa', 'mastercard'])
            ->call('save')
            ->assertSee('Midtrans Snap Production');

        $gateway = PaymentGateway::where('company_id', $this->company->id)->firstOrFail();

        $this->assertSame('local_api', $gateway->driver);
        $this->assertSame('https://api.midtrans.com', $gateway->config['base_url']);
        $this->assertSame('secret-key', $gateway->config['api_key']);
        $this->assertSame('M-001', $gateway->config['merchant_id']);
        $this->assertSame(['qris', 'virtual_account'], $gateway->config['methods']);
        $this->assertSame(['visa', 'mastercard'], $gateway->accepted_credit_cards);
    }

    public function test_creating_a_gateway_requires_a_base_url_for_the_local_api_driver(): void
    {
        Livewire::test(TallStackPaymentGateways::class, ['company' => $this->company])
            ->call('create')
            ->set('name', 'Broken Local API')
            ->set('driver', 'local_api')
            ->call('save')
            ->assertHasErrors(['configBaseUrl']);

        $this->assertSame(0, PaymentGateway::where('company_id', $this->company->id)->count());
    }

    public function test_a_staff_member_cannot_view_the_payment_gateways_page_even_though_mutating_roles_allow_writes(): void
    {
        // Staff is a CompanyRole::mutatingRoles() member (create/update
        // allowed by the generic Gate::before hook every BelongsToCompany
        // model gets), but this page's own viewSettings() gate (Owner/Admin
        // only) blocks it from even opening the page — proving the two
        // gates are independent, exactly as documented on the component.
        $staff = User::factory()->create();
        $this->company->users()->attach($staff, ['role' => 'staff']);
        $this->actingAs($staff);

        Livewire::test(TallStackPaymentGateways::class, ['company' => $this->company])
            ->assertStatus(403);
    }

    public function test_edit_populates_the_form_from_an_existing_gateways_config_and_save_updates_it(): void
    {
        $gateway = PaymentGateway::create([
            'company_id' => $this->company->id,
            'name' => 'Local API (Indonesia)',
            'driver' => 'local_api',
            'is_enabled' => true,
            'config' => ['base_url' => 'https://old.example.test', 'api_key' => 'old-key', 'merchant_id' => 'M-OLD', 'methods' => ['qris']],
            'accepted_credit_cards' => ['visa'],
        ]);

        Livewire::test(TallStackPaymentGateways::class, ['company' => $this->company])
            ->call('edit', $gateway->id)
            ->assertSet('name', 'Local API (Indonesia)')
            ->assertSet('configBaseUrl', 'https://old.example.test')
            ->assertSet('configMerchantId', 'M-OLD')
            ->set('configBaseUrl', 'https://new.example.test')
            ->call('save');

        $this->assertSame('https://new.example.test', $gateway->refresh()->config['base_url']);
    }

    public function test_editing_a_gateway_from_another_company_is_silently_refused(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co-2', 'currency_code' => 'USD']);
        $otherGateway = PaymentGateway::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Company Gateway',
            'driver' => 'manual',
            'is_enabled' => true,
            'config' => [],
        ]);

        Livewire::test(TallStackPaymentGateways::class, ['company' => $this->company])
            ->call('edit', $otherGateway->id)
            ->assertSet('editingId', null)
            ->assertSet('name', null);
    }

    public function test_test_connection_reports_not_configured_for_a_local_api_gateway_missing_credentials(): void
    {
        $gateway = PaymentGateway::create([
            'company_id' => $this->company->id,
            'name' => 'Local API (Indonesia)',
            'driver' => 'local_api',
            'is_enabled' => true,
            'config' => [],
        ]);

        Livewire::test(TallStackPaymentGateways::class, ['company' => $this->company])
            ->call('testConnection', $gateway->id)
            ->assertSee('Not configured');
    }

    public function test_test_connection_reports_no_driver_for_an_unimplemented_driver(): void
    {
        $gateway = PaymentGateway::create([
            'company_id' => $this->company->id,
            'name' => 'Stripe',
            'driver' => 'stripe',
            'is_enabled' => true,
            'config' => [],
        ]);

        Livewire::test(TallStackPaymentGateways::class, ['company' => $this->company])
            ->call('testConnection', $gateway->id)
            ->assertSee('No driver for this gateway type');
    }

    public function test_test_connection_for_a_gateway_from_another_company_is_silently_refused(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co-3', 'currency_code' => 'USD']);
        $otherGateway = PaymentGateway::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Company Gateway',
            'driver' => 'local_api',
            'is_enabled' => true,
            'config' => [],
        ]);

        Livewire::test(TallStackPaymentGateways::class, ['company' => $this->company])
            ->call('testConnection', $otherGateway->id)
            ->assertDontSee('Not configured');
    }
}
