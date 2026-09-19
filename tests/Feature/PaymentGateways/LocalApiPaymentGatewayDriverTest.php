<?php

namespace Tests\Feature\PaymentGateways;

use App\Enums\LocalPaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\PaymentGateways\ChargeRequest;
use App\Services\PaymentGateways\GatewayNotConfiguredException;
use App\Services\PaymentGateways\LocalApiPaymentGatewayDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * App\Services\PaymentGateways\LocalApiPaymentGatewayDriver — the stubbed
 * driver for the Indonesian in-house payment API mentioned in
 * docs/engineering.md §3.4. Every call goes through
 * Illuminate\Support\Facades\Http, so Http::fake() exercises the real
 * request/response mapping without a live provider.
 */
class LocalApiPaymentGatewayDriverTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private PaymentGateway $gateway;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'IDR']);
        $this->gateway = PaymentGateway::create([
            'company_id' => $this->company->id,
            'name' => 'Local API',
            'driver' => 'local_api',
            'config' => [
                'base_url' => 'https://api.example-id-gateway.test',
                'api_key' => 'secret-key',
                'merchant_id' => 'merchant-1',
            ],
        ]);
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'number' => 'INV-0001',
        ]);
        $this->payment = Payment::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => 150000,
            'currency_code' => 'IDR',
            'status' => 'pending',
        ]);
    }

    public function test_charge_posts_to_the_charges_endpoint_and_maps_a_successful_response(): void
    {
        Http::fake([
            'api.example-id-gateway.test/v1/charges' => Http::response([
                'id' => 'chg_123',
                'status' => 'success',
                'instructions' => ['va_number' => '1234567890', 'bank_code' => 'BCA'],
            ]),
        ]);

        $driver = new LocalApiPaymentGatewayDriver($this->gateway);
        $result = $driver->charge($this->payment, new ChargeRequest(LocalPaymentMethod::VirtualAccount, bankCode: 'BCA'));

        $this->assertSame(PaymentStatus::Completed, $result->status);
        $this->assertSame('chg_123', $result->providerReference);
        $this->assertSame('1234567890', $result->instructions['va_number']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.example-id-gateway.test/v1/charges'
                && $request->hasHeader('Authorization', 'Bearer secret-key')
                && $request['merchant_id'] === 'merchant-1'
                && $request['method'] === 'virtual_account'
                && $request['bank_code'] === 'BCA'
                && (float) $request['amount'] === 150000.0;
        });
    }

    public function test_charge_throws_when_the_gateway_has_no_base_url_or_api_key(): void
    {
        $unconfigured = PaymentGateway::create([
            'company_id' => $this->company->id,
            'name' => 'Local API (unset)',
            'driver' => 'local_api',
        ]);

        $this->expectException(GatewayNotConfiguredException::class);

        (new LocalApiPaymentGatewayDriver($unconfigured))->charge($this->payment, new ChargeRequest(LocalPaymentMethod::Qris));
    }

    public function test_check_status_calls_the_charge_status_endpoint(): void
    {
        $this->payment->forceFill(['gateway_reference' => 'chg_123'])->save();

        Http::fake([
            'api.example-id-gateway.test/v1/charges/chg_123' => Http::response(['id' => 'chg_123', 'status' => 'pending']),
        ]);

        $result = (new LocalApiPaymentGatewayDriver($this->gateway))->checkStatus($this->payment);

        $this->assertSame(PaymentStatus::Pending, $result->status);
    }

    public function test_test_connection_is_true_when_the_ping_succeeds(): void
    {
        Http::fake(['api.example-id-gateway.test/v1/ping' => Http::response([], 200)]);

        $this->assertTrue((new LocalApiPaymentGatewayDriver($this->gateway))->testConnection());
    }

    public function test_test_connection_is_false_when_the_ping_fails(): void
    {
        Http::fake(['api.example-id-gateway.test/v1/ping' => Http::response([], 503)]);

        $this->assertFalse((new LocalApiPaymentGatewayDriver($this->gateway))->testConnection());
    }

    public function test_handle_webhook_maps_a_raw_payload_without_making_a_request(): void
    {
        $result = (new LocalApiPaymentGatewayDriver($this->gateway))->handleWebhook([
            'id' => 'chg_999',
            'status' => 'expired',
        ]);

        $this->assertSame(PaymentStatus::Failed, $result->status);
        $this->assertSame('chg_999', $result->providerReference);
    }
}
