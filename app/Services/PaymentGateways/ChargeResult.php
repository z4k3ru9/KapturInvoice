<?php

namespace App\Services\PaymentGateways;

use App\Enums\PaymentStatus;

/**
 * A driver's answer to "what happened" — returned from charge(),
 * checkStatus(), and handleWebhook() alike so callers can treat all three
 * the same way. `instructions` carries whatever the client needs to
 * finish paying (e.g. a virtual-account number + bank code, or a QRIS
 * string) — empty for methods that resolve immediately (a card charge).
 */
final class ChargeResult
{
    /**
     * @param  array<string, mixed>  $instructions
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public PaymentStatus $status,
        public ?string $providerReference = null,
        public array $instructions = [],
        public array $raw = [],
    ) {}
}
