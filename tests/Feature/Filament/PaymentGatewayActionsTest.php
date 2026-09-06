<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PaymentGateways\Pages\ListPaymentGateways;
use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "Test Connection" table action closes the ⚠️ flagged in
 * docs/filament-admin-layout-design.md §3.4 — it now actually calls the
 * configured driver rather than not existing at all.
 */
class PaymentGatewayActionsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'IDR']);
        $this->company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    public function test_test_connection_action_reports_success(): void
    {
        Http::fake(['api.example-id-gateway.test/v1/ping' => Http::response([], 200)]);

        $gateway = PaymentGateway::create([
            'company_id' => $this->company->id,
            'name' => 'Local API',
            'driver' => 'local_api',
            'config' => ['base_url' => 'https://api.example-id-gateway.test', 'api_key' => 'secret'],
        ]);

        Livewire::test(ListPaymentGateways::class)
            ->callTableAction('testConnection', $gateway)
            ->assertNotified();
    }

    public function test_test_connection_action_reports_not_configured(): void
    {
        $gateway = PaymentGateway::create([
            'company_id' => $this->company->id,
            'name' => 'Local API',
            'driver' => 'local_api',
        ]);

        Livewire::test(ListPaymentGateways::class)
            ->callTableAction('testConnection', $gateway)
            ->assertNotified('Not configured');
    }
}
