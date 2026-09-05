<?php

namespace Tests\Feature\PaymentGateways;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Http\Controllers\PaymentGatewayWebhookController — the async
 * status-callback endpoint gateways (Virtual Account/QRIS especially)
 * need. Posted without a CSRF token, like a real provider would, to
 * confirm the bootstrap/app.php `webhooks/*` exemption actually works.
 */
class PaymentGatewayWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_updates_the_matching_payments_status(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'IDR']);
        $gateway = PaymentGateway::create([
            'company_id' => $company->id,
            'name' => 'Local API',
            'driver' => 'local_api',
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $invoice = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'type' => 'invoice', 'status' => 'sent', 'number' => 'INV-0001']);
        $payment = Payment::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'status' => 'pending',
            'gateway_reference' => 'chg_123',
        ]);

        $this->post("/webhooks/payment-gateways/{$gateway->id}", [
            'id' => 'chg_123',
            'status' => 'success',
        ])->assertNoContent();

        $this->assertSame('completed', $payment->fresh()->status->value);
    }

    public function test_webhook_for_an_unknown_reference_is_a_no_op(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'IDR']);
        $gateway = PaymentGateway::create(['company_id' => $company->id, 'name' => 'Local API', 'driver' => 'local_api']);

        $this->post("/webhooks/payment-gateways/{$gateway->id}", [
            'id' => 'chg_does_not_exist',
            'status' => 'success',
        ])->assertNoContent();
    }
}
