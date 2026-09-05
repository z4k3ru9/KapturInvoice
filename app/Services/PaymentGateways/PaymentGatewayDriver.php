<?php

namespace App\Services\PaymentGateways;

use App\Models\Payment;

/**
 * The contract every gateway driver implements — see
 * docs/filament-admin-layout-design.md §3.4. `App\Models\PaymentGateway`
 * just stores config; a driver is what actually talks to a provider's
 * API. Resolve one via `App\Services\PaymentGateways\PaymentGatewayManager`
 * rather than constructing a driver directly.
 */
interface PaymentGatewayDriver
{
    /**
     * Initiate a charge for the given Payment. Throws
     * GatewayNotConfiguredException if the gateway is missing required
     * config, or an Illuminate\Http\Client\RequestException if the
     * provider's API itself rejects the request.
     */
    public function charge(Payment $payment, ChargeRequest $request): ChargeResult;

    /** Poll the provider for a charge's current status. */
    public function checkStatus(Payment $payment): ChargeResult;

    /**
     * Map a provider's webhook payload onto a ChargeResult. Callers (see
     * App\Http\Controllers\PaymentGatewayWebhookController) are
     * responsible for finding the Payment the result applies to.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): ChargeResult;

    /** Used by the "Test Connection" admin action — true if reachable/configured. */
    public function testConnection(): bool;
}
