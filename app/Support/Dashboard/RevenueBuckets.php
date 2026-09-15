<?php

namespace App\Support\Dashboard;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * The revenue-trend bucket computation shared by the dashboard's chart and
 * its accessible-table alternative — see
 * docs/rebuild/outputs/ui-rebuild/18-stitch-ui-gap-analysis/01-shell-dashboard.md D5/
 * D6. Kept out of the presentation layer so the chart and the table sum
 * the exact same numbers, and so the query logic is unit-testable without
 * Livewire.
 *
 * "Invoiced" reads the legacy `invoices` table (`type = invoice`, not
 * Draft) by `invoice_date` — an interim stand-in until the canonical
 * Phase 04 invoice pipeline fully replaces it; labeled "Invoiced (legacy)"
 * on the chart for that reason.
 */
class RevenueBuckets
{
    /**
     * @param  array{start: Carbon, end: Carbon, group_by: string}  $period
     * @return array{labels: list<string>, invoiced: list<float>, collected: list<float>}
     */
    public static function forPeriod(array $period): array
    {
        $isMonthly = $period['group_by'] === 'month';

        $rangeStart = $isMonthly ? $period['start']->copy()->startOfMonth() : $period['start']->copy()->startOfDay();
        $rangeEnd = $isMonthly ? $period['end']->copy()->endOfMonth() : $period['end']->copy()->endOfDay();

        // Two queries total for the whole period (was two per bucket - up
        // to 60+ queries for a month view) - both invoice_date and
        // payment_date are `date`-cast columns but stored with a
        // "Y-m-d 00:00:00" time suffix (Eloquent's date-attribute write
        // path uses the connection's full datetime format, not a
        // date-only one), so the whereBetween bounds below use full
        // datetime strings - a plain toDateString() upper bound would be
        // lexicographically LESS than "<same day> 00:00:00" and silently
        // exclude that day's own rows. Otherwise exactly equivalent to
        // the original per-bucket whereDate()/whereYear()+whereMonth()
        // filters, just computed in PHP afterward instead of re-querying
        // per bucket. Grouping in PHP rather than SQL GROUP BY keeps this
        // portable across the sqlite (dev/test) and MySQL (production)
        // drivers this app supports without a driver-specific date-format
        // function.
        $rangeStartBound = $rangeStart->format('Y-m-d 00:00:00');
        $rangeEndBound = $rangeEnd->format('Y-m-d 23:59:59');

        $invoicedByKey = Invoice::query()
            ->where('type', InvoiceType::Invoice)
            ->where('status', '!=', InvoiceStatus::Draft)
            ->whereBetween('invoice_date', [$rangeStartBound, $rangeEndBound])
            ->get(['invoice_date', 'total'])
            ->groupBy(fn (Invoice $invoice) => self::bucketKey($invoice->invoice_date, $isMonthly))
            ->map(fn ($group) => (float) $group->sum('total'));

        $collectedByKey = Payment::query()
            ->whereIn('status', [PaymentStatus::Completed, PaymentStatus::Verified])
            ->whereBetween('payment_date', [$rangeStartBound, $rangeEndBound])
            ->get(['payment_date', 'amount'])
            ->groupBy(fn (Payment $payment) => self::bucketKey($payment->payment_date, $isMonthly))
            ->map(fn ($group) => (float) $group->sum('amount'));

        $labels = [];
        $invoiced = [];
        $collected = [];

        $cursor = $rangeStart->copy();

        while ($cursor->lte($period['end'])) {
            $key = self::bucketKey($cursor, $isMonthly);
            $labels[] = $isMonthly ? $cursor->format('M Y') : $cursor->format('M j');
            $invoiced[] = $invoicedByKey->get($key, 0.0);
            $collected[] = $collectedByKey->get($key, 0.0);

            $isMonthly ? $cursor->addMonth() : $cursor->addDay();
        }

        return ['labels' => $labels, 'invoiced' => $invoiced, 'collected' => $collected];
    }

    private static function bucketKey(Carbon $date, bool $isMonthly): string
    {
        return $isMonthly ? $date->format('Y-m') : $date->format('Y-m-d');
    }
}
