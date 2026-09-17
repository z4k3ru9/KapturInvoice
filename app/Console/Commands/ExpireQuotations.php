<?php

namespace App\Console\Commands;

use App\Actions\Sales\TransitionQuotationStatus;
use App\Enums\QuotationStatus;
use App\Models\Quotation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Scheduled daily (see routes/console.php) to close the "no automatic
 * quotation expiry" gap flagged during Phase 03 (Sales and Job) — until now,
 * QuotationStatus::Expired was reachable only via a manual "Mark expired"
 * action. Only a Sent quotation past its valid_until date is eligible
 * (QuotationStatus::canTransitionTo() already refuses any other source
 * state), so this can never expire a Draft/Approved quotation that was
 * simply never sent.
 */
class ExpireQuotations extends Command
{
    protected $signature = 'quotations:expire';

    protected $description = 'Mark Sent quotations past their valid_until date as Expired.';

    public function handle(TransitionQuotationStatus $transition): int
    {
        $expired = 0;

        Quotation::query()
            ->where('status', QuotationStatus::Sent)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', Carbon::today())
            ->each(function (Quotation $quotation) use ($transition, &$expired) {
                $transition->transition($quotation, QuotationStatus::Expired);
                $expired++;
            });

        $this->info("Expired {$expired} quotation(s).");

        return self::SUCCESS;
    }
}
