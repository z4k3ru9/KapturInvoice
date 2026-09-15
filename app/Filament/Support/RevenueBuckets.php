<?php

namespace App\Filament\Support;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * The revenue-trend bucket computation shared by RevenueTrendChart and
 * RevenueTrendTable — see
 * docs/rebuild/outputs/18-stitch-ui-gap-analysis/01-shell-dashboard.md D5/
 * D6. Kept out of the widgets so both the chart and its accessible-table
 * alternative sum the exact same numbers, and so the query logic is
 * unit-testable without Livewire.
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
        $labels = [];
        $invoiced = [];
        $collected = [];

        $cursor = $period['group_by'] === 'month'
            ? $period['start']->copy()->startOfMonth()
            : $period['start']->copy()->startOfDay();

        while ($cursor->lte($period['end'])) {
            if ($period['group_by'] === 'month') {
                $labels[] = $cursor->format('M Y');
                $invoiced[] = (float) Invoice::query()
                    ->where('type', InvoiceType::Invoice)
                    ->where('status', '!=', InvoiceStatus::Draft)
                    ->whereYear('invoice_date', $cursor->year)
                    ->whereMonth('invoice_date', $cursor->month)
                    ->sum('total');
                $collected[] = (float) Payment::query()
                    ->where('status', PaymentStatus::Completed)
                    ->whereYear('payment_date', $cursor->year)
                    ->whereMonth('payment_date', $cursor->month)
                    ->sum('amount');

                $cursor->addMonth();
            } else {
                $labels[] = $cursor->format('M j');
                $invoiced[] = (float) Invoice::query()
                    ->where('type', InvoiceType::Invoice)
                    ->where('status', '!=', InvoiceStatus::Draft)
                    ->whereDate('invoice_date', $cursor->toDateString())
                    ->sum('total');
                $collected[] = (float) Payment::query()
                    ->where('status', PaymentStatus::Completed)
                    ->whereDate('payment_date', $cursor->toDateString())
                    ->sum('amount');

                $cursor->addDay();
            }
        }

        return ['labels' => $labels, 'invoiced' => $invoiced, 'collected' => $collected];
    }
}
