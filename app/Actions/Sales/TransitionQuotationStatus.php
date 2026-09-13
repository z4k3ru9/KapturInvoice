<?php

namespace App\Actions\Sales;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use RuntimeException;

/**
 * Every quotation status change except acceptance goes through here, so
 * `QuotationStatus::canTransitionTo()` (docs/rebuild/specs/
 * 03-sales-and-job/Specs.md's state list) is the single source of truth
 * rather than each caller trusting its own logic. Acceptance is handled
 * separately by AcceptQuotation, since it also has to resolve the
 * customer PO / Customer Order Confirmation.
 */
class TransitionQuotationStatus
{
    public function transition(Quotation $quotation, QuotationStatus $to): Quotation
    {
        if ($to === QuotationStatus::Accepted) {
            throw new RuntimeException('Use AcceptQuotation to move a quotation to Accepted.');
        }

        if (! $quotation->status->canTransitionTo($to)) {
            throw new RuntimeException(
                "Quotation cannot move from {$quotation->status->value} to {$to->value}."
            );
        }

        $timestampField = match ($to) {
            QuotationStatus::Sent => 'sent_at',
            QuotationStatus::Rejected => 'rejected_at',
            QuotationStatus::Expired => 'expired_at',
            QuotationStatus::Cancelled => 'cancelled_at',
            default => null,
        };

        $quotation->status = $to;

        if ($timestampField !== null) {
            $quotation->{$timestampField} = now();
        }

        $quotation->save();

        return $quotation;
    }
}
