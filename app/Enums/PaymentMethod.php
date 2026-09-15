<?php

namespace App\Enums;

/**
 * "Payment methods: bank transfer, cheque, manual." —
 * docs/rebuild/specs/04-billing-and-receivables/Specs.md. Stored in the
 * same free-form `payments.method` string column legacy-imported rows
 * already use (no schema change needed) — new payments recorded via
 * App\Actions\Receivables\RecordCustomerPayment always use one of these
 * values.
 */
enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank transfer',
            self::Cheque => 'Cheque',
            self::Manual => 'Manual',
        };
    }
}
