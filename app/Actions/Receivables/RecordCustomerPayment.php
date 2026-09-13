<?php

namespace App\Actions\Receivables;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use RuntimeException;

/**
 * "Payment stores mandatory proof, date, amount, currency, reference where
 * applicable, client, optional job, and notes." —
 * docs/rebuild/specs/04-billing-and-receivables/Specs.md,
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §4: "Bank transfer, cheque, and
 * manual payments all require proof upload before verification." Every
 * payment recorded here starts at PaymentStatus::Pending — it only reaches
 * PaymentStatus::Verified via App\Actions\Receivables\VerifyCustomerPayment.
 * `invoice_id` is deliberately left null: a new payment is allocated across
 * one or more invoices via PaymentAllocation, not the legacy single-invoice
 * column.
 */
class RecordCustomerPayment
{
    /**
     * @param  array{
     *     company_id: int,
     *     client_id: int,
     *     sales_order_id?: int|null,
     *     method: PaymentMethod,
     *     amount: float|string,
     *     currency_code: string,
     *     payment_date: \DateTimeInterface|string,
     *     proof_path: string,
     *     reference?: string|null,
     *     notes?: string|null,
     * }  $data
     */
    public function record(array $data): Payment
    {
        if (blank($data['proof_path'] ?? null)) {
            throw new RuntimeException('A proof of payment upload is required before a payment can be recorded.');
        }

        return Payment::create([
            'company_id' => $data['company_id'],
            'client_id' => $data['client_id'],
            'sales_order_id' => $data['sales_order_id'] ?? null,
            'invoice_id' => null,
            'method' => $data['method']->value,
            'amount' => $data['amount'],
            'currency_code' => $data['currency_code'],
            'payment_date' => $data['payment_date'],
            'proof_path' => $data['proof_path'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => PaymentStatus::Pending,
        ]);
    }
}
