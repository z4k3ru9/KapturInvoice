<?php

namespace App\Services\PaymentGateways;

use App\Enums\LocalPaymentMethod;

/**
 * What to charge a client for, and how — passed into
 * PaymentGatewayDriver::charge(). Deliberately provider-agnostic (the
 * driver decides how to map this onto its own request shape) so callers
 * don't need to know which driver is behind a given PaymentGateway.
 */
final class ChargeRequest
{
    public function __construct(
        public LocalPaymentMethod $method,
        public ?string $bankCode = null,
    ) {}
}
