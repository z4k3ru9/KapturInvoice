<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Carbon;

/**
 * The next-due-date arithmetic for a recurring Invoice template
 * (`is_recurring = true`), extracted from
 * App\Livewire\TallStackRecurringInvoices::estimateNextDate() — that
 * method's own docblock used to note "no real automatic-generation
 * scheduler exists in this codebase, this is display-only." Now that
 * App\Console\Commands\GenerateDueRecurringInvoices exists, both the
 * Livewire display estimate and the real scheduling decision share this
 * one calculation rather than drifting apart.
 */
class RecurringInvoiceSchedule
{
    public function nextDueDate(Invoice $template): ?Carbon
    {
        $anchor = $template->recurring_last_sent_at?->copy() ?? $template->recurring_start_date?->copy();

        if (! $anchor) {
            return null;
        }

        $next = match (strtolower((string) $template->recurring_frequency)) {
            'weekly' => $anchor->addWeek(),
            'monthly' => $anchor->addMonthNoOverflow(),
            'quarterly' => $anchor->addMonthsNoOverflow(3),
            'annually', 'yearly' => $anchor->addYearNoOverflow(),
            default => null,
        };

        if ($next && $template->recurring_end_date && $next->greaterThan($template->recurring_end_date)) {
            return null;
        }

        return $next;
    }

    public function isDue(Invoice $template, ?Carbon $asOf = null): bool
    {
        $next = $this->nextDueDate($template);

        return $next !== null && $next->lessThanOrEqualTo($asOf ?? Carbon::today());
    }
}
