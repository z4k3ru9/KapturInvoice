<?php

namespace App\Actions\Billing;

use App\Models\Credit;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Permanently purges a Credit that has never been applied (its `balance`
 * still equals its face `amount`) — see App\Actions\Billing\
 * ForceDeleteInvoice's docblock for the shared rationale.
 *
 * Ported from the equivalent table-level guard in the pre-TallStackUI
 * Filament admin (dropped, unreplaced, when Filament was removed).
 */
class ForceDeleteCredit
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    /** Safe iff: never applied (balance still equals the credit's face amount). */
    public static function isSafeToForceDelete(Credit $credit): bool
    {
        return (float) $credit->balance === (float) $credit->amount;
    }

    public function forceDelete(Credit $credit, User $actor): void
    {
        if (! $actor->can('forceDelete', $credit)) {
            throw new RuntimeException('Only the company Owner may permanently delete a credit.');
        }

        if (! self::isSafeToForceDelete($credit)) {
            throw new RuntimeException('Only a credit that has never been applied can be permanently deleted.');
        }

        DB::transaction(function () use ($credit) {
            $this->auditLogger->record(
                $credit->company,
                'credit.force_deleted',
                $credit,
                $credit->getAttributes(),
                [],
            );

            $credit->forceDelete();
        });
    }
}
