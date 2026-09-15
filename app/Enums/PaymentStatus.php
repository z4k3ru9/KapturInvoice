<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    // Phase 04 (docs/rebuild/specs/04-billing-and-receivables): the new
    // verify/allocate/receipt workflow. `Completed` above stays exactly as
    // legacy-imported payments already recorded it (they're already
    // settled history); every payment recorded from here on via
    // App\Actions\Receivables\RecordCustomerPayment starts at `Pending`
    // and only reaches `Verified` through
    // App\Actions\Receivables\VerifyCustomerPayment.
    case Verified = 'verified';
    case Reversed = 'reversed';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Completed, self::Verified => 'success',
            self::Failed, self::Reversed => 'danger',
            self::Refunded => 'gray',
        };
    }
}
