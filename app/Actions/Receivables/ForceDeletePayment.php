<?php

namespace App\Actions\Receivables;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Permanently purges a still-Pending, never-verified/allocated/receipted
 * Payment row — see App\Actions\Billing\ForceDeleteInvoice's docblock for
 * the shared rationale ("a payment is an event; a verified one and its
 * receipt/allocations are never physically deleted" — this only ever
 * applies before that event is real).
 *
 * Ported from `App\Filament\Resources\Payments\Tables\
 * PaymentsTable::isSafeToForceDelete()` (dropped, unreplaced, when
 * Filament was removed).
 */
class ForceDeletePayment
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    /** Safe iff: still Pending, and never receipted, allocated, or verified. */
    public static function isSafeToForceDelete(Payment $payment): bool
    {
        return $payment->status === PaymentStatus::Pending
            && ! $payment->receipt()->exists()
            && ! $payment->allocations()->exists()
            && ! $payment->verificationEvents()->exists();
    }

    public function forceDelete(Payment $payment, User $actor): void
    {
        if (! $actor->can('forceDelete', $payment)) {
            throw new RuntimeException('Only the company Owner may permanently delete a payment.');
        }

        if (! self::isSafeToForceDelete($payment)) {
            throw new RuntimeException('Only a Pending payment with no receipt, allocations, or verification history can be permanently deleted.');
        }

        DB::transaction(function () use ($payment) {
            $this->auditLogger->record(
                $payment->company,
                'payment.force_deleted',
                $payment,
                $payment->getAttributes(),
                [],
            );

            $payment->forceDelete();
        });
    }
}
