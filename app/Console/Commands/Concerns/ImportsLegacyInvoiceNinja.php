<?php

namespace App\Console\Commands\Concerns;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;

/**
 * Shared helpers for `import:invoiceninja-v4`/`import:invoiceninja-v5` — see
 * docs/data-import.md for the mapping this backs. Both legacy schemas store
 * a numeric status id whose meaning isn't safely comparable across the two
 * versions (v4's `invoice_statuses` lookup table doesn't necessarily share
 * v5's per-entity-type numbering — confirmed by cross-checking real
 * `axentech_ninj876.sql` rows against their balance/paid_to_date, which
 * didn't line up with v4's authoritative 1=Draft..6=Paid table). Deriving
 * status from the objective financial state instead avoids trusting either
 * source's status id at all.
 */
trait ImportsLegacyInvoiceNinja
{
    /** @var array<string, int> */
    protected array $importStats = [];

    protected function bump(string $key, int $by = 1): void
    {
        $this->importStats[$key] = ($this->importStats[$key] ?? 0) + $by;
    }

    protected function printStats(): void
    {
        $this->newLine();
        $this->table(['Table', 'Rows imported'], collect($this->importStats)
            ->map(fn (int $count, string $key) => [$key, $count])
            ->values()
            ->all());
    }

    /**
     * @return array{status: InvoiceStatus, cancelled: bool}
     */
    protected function resolveDocumentStatus(
        float $amount,
        float $balance,
        float $paidToDate,
        ?string $sentAt,
        ?string $viewedAt,
    ): array {
        $epsilon = 0.01;

        if ($amount <= $epsilon) {
            return ['status' => InvoiceStatus::Cancelled, 'cancelled' => true];
        }

        if ($balance <= $epsilon) {
            if ($paidToDate + $epsilon >= $amount) {
                return ['status' => InvoiceStatus::Paid, 'cancelled' => false];
            }

            // Balance was zeroed out without a matching payment — a voided
            // invoice, not a paid one.
            return ['status' => InvoiceStatus::Cancelled, 'cancelled' => true];
        }

        if ($balance < $amount - $epsilon) {
            return ['status' => InvoiceStatus::Partial, 'cancelled' => false];
        }

        return ['status' => match (true) {
            filled($viewedAt) => InvoiceStatus::Viewed,
            filled($sentAt) => InvoiceStatus::Sent,
            default => InvoiceStatus::Draft,
        }, 'cancelled' => false];
    }

    /**
     * Both v4's `payment_statuses` lookup table (authoritative for v4) and
     * a same-numbering spot-check against v5's real payment rows (payments
     * with no matching `paymentables` row — i.e. never applied to an
     * invoice — consistently carry status_id=2) agree on this mapping, so
     * it's reused for both importers. The target PaymentStatus enum has no
     * "voided"/"partially refunded" case, so those collapse to the closest
     * fit.
     */
    protected function mapLegacyPaymentStatus(?int $legacyStatusId): PaymentStatus
    {
        return match ($legacyStatusId) {
            1 => PaymentStatus::Pending,
            2 => PaymentStatus::Failed, // Voided
            3 => PaymentStatus::Failed,
            5, 6 => PaymentStatus::Refunded, // Partially Refunded, Refunded
            default => PaymentStatus::Completed, // 4 = Completed
        };
    }

    protected function money(mixed $value): float
    {
        return round((float) $value, 2);
    }
}
