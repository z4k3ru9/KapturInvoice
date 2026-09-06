<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\PaymentGateways\GatewayNotConfiguredException;
use App\Services\PaymentGateways\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives async status callbacks from a gateway (essential for Virtual
 * Account/QRIS, which settle after the initial charge call returns) — see
 * docs/filament-admin-layout-design.md §3.4. Identified by the
 * PaymentGateway's own id (route-bound), not by tenant/auth: the provider
 * calling this has no Filament session, so it's excluded from CSRF
 * verification (see bootstrap/app.php) rather than gated behind `auth`.
 *
 * ⚠️ Does not yet verify a signature on the payload — the real provider's
 * signing scheme isn't known yet; add that check in
 * LocalApiPaymentGatewayDriver::handleWebhook() once it is, rather than
 * trusting an unsigned POST in production.
 */
class PaymentGatewayWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $paymentGateway, PaymentGatewayManager $manager): Response
    {
        try {
            $result = $manager->driverFor($paymentGateway)->handleWebhook($request->all());
        } catch (GatewayNotConfiguredException $e) {
            Log::warning('Payment gateway webhook received for an unconfigured gateway', [
                'payment_gateway_id' => $paymentGateway->id,
                'message' => $e->getMessage(),
            ]);

            return response()->noContent();
        }

        if (! $result->providerReference) {
            return response()->noContent();
        }

        Payment::query()
            ->where('company_id', $paymentGateway->company_id)
            ->where('gateway_reference', $result->providerReference)
            ->first()
            ?->forceFill(['status' => $result->status])
            ->save();

        return response()->noContent();
    }
}
