<?php

namespace App\Enums;

/**
 * The payment methods the Indonesian in-house payment API (see
 * docs/filament-admin-layout-design.md §3.4 and
 * App\Services\PaymentGateways\LocalApiPaymentGatewayDriver) is expected
 * to support — a `local_api` gateway's `config.methods` selects among
 * these.
 */
enum LocalPaymentMethod: string
{
    case VirtualAccount = 'virtual_account';
    case Qris = 'qris';
    case Card = 'card';

    public function getLabel(): string
    {
        return match ($this) {
            self::VirtualAccount => 'Virtual Account (bank transfer)',
            self::Qris => 'QRIS',
            self::Card => 'Credit/debit card',
        };
    }
}
