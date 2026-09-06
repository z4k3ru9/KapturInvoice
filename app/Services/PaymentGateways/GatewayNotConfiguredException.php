<?php

namespace App\Services\PaymentGateways;

use RuntimeException;

/**
 * Thrown when a gateway is missing the config a driver needs (e.g. a
 * `local_api` gateway with no `base_url`/`api_key` filled in yet) — kept
 * distinct from a generic RuntimeException so callers (Filament actions,
 * the webhook controller) can catch it and show a "not configured" state
 * rather than a raw error.
 */
class GatewayNotConfiguredException extends RuntimeException {}
