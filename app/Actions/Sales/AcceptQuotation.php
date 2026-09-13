<?php

namespace App\Actions\Sales;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Services\DocumentNumberGenerator;
use RuntimeException;

/**
 * "Accept a quotation with or without a customer PO. Record a supplied
 * customer PO or create an internal Customer Order Confirmation; never
 * represent the generated confirmation as customer-issued." —
 * docs/rebuild/specs/03-sales-and-job/Specs.md,
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §2.
 */
class AcceptQuotation
{
    public function __construct(private DocumentNumberGenerator $numberGenerator) {}

    public function accept(
        Quotation $quotation,
        ?string $customerPoNumber = null,
        ?\DateTimeInterface $customerPoDate = null,
    ): Quotation {
        if (! $quotation->status->canTransitionTo(QuotationStatus::Accepted)) {
            throw new RuntimeException(
                "Quotation cannot move from {$quotation->status->value} to accepted."
            );
        }

        $isSystemGenerated = blank($customerPoNumber);

        if ($isSystemGenerated) {
            // A Customer Order Confirmation (`COC`) — an internal document
            // standing in for a PO the customer never supplied. It must
            // never be presented as if the customer issued it.
            $customerPoNumber = $this->numberGenerator->next($quotation->company, 'coc');
        }

        $quotation->forceFill([
            'status' => QuotationStatus::Accepted,
            'accepted_at' => now(),
            'customer_po_number' => $customerPoNumber,
            'customer_po_date' => $customerPoDate ?? now(),
            'customer_po_is_system_generated' => $isSystemGenerated,
        ])->save();

        return $quotation;
    }
}
