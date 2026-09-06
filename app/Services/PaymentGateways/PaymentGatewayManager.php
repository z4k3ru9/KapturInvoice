<?php

namespace App\Services\PaymentGateways;

use App\Models\PaymentGateway;
use RuntimeException;

/**
 * Resolves the right PaymentGatewayDriver for a PaymentGateway's `driver`
 * column — the one seam to extend when a real Stripe/Midtrans/Xendit/
 * PayPal integration is added later (see
 * docs/filament-admin-layout-design.md §3.4). Only `local_api` is wired
 * up so far.
 */
class PaymentGatewayManager
{
    public function driverFor(PaymentGateway $gateway): PaymentGatewayDriver
    {
        return match ($gateway->driver) {
            'local_api' => new LocalApiPaymentGatewayDriver($gateway),
            default => throw new RuntimeException(
                "No driver is implemented for gateway type [{$gateway->driver}] yet — only 'local_api' is wired up so far."
            ),
        };
    }
}
