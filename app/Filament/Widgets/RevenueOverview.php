<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Filament\Support\DashboardPeriod;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * The welcome-page stats row — see docs/filament-admin-layout-design.md
 * §9. "Total revenue" respects the Dashboard's period filter (payments
 * received in that window); "Pending"/"Overdue" are deliberately *not*
 * period-filtered — they're a live snapshot of what needs attention right
 * now, same as InvoiceNinja's own dashboard.
 */
class RevenueOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    // These stats are a handful of cheap, tenant-scoped aggregate queries
    // (not an N+1 risk) — lazy-loading gains nothing here, and Filament's
    // lazy placeholder path has a column-span-array/attribute-bag bug
    // that 500s when a widget's columnSpan can't collapse to a plain
    // string (see the design doc §9's note on this).
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $company = Filament::getTenant();
        $currency = $company?->currency_code ?: '';
        $period = DashboardPeriod::resolve($this->pageFilters);

        $revenue = Payment::query()
            ->where('status', PaymentStatus::Completed)
            ->whereBetween('payment_date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->sum('amount');

        $pending = $this->openInvoicesQuery()->where(function (Builder $query) {
            $query->whereNull('due_date')->orWhere('due_date', '>=', today());
        });
        $overdue = $this->openInvoicesQuery()->where('due_date', '<', today());

        return [
            Stat::make('Total revenue', trim("{$currency} ".number_format((float) $revenue, 2)))
                ->description($period['label'])
                ->color('success'),
            Stat::make('Pending invoices', (clone $pending)->count())
                ->description(trim("{$currency} ".number_format((float) (clone $pending)->sum('balance'), 2)).' outstanding')
                ->color('warning'),
            Stat::make('Overdue invoices', (clone $overdue)->count())
                ->description(trim("{$currency} ".number_format((float) (clone $overdue)->sum('balance'), 2)).' overdue')
                ->color('danger'),
        ];
    }

    protected function openInvoicesQuery(): Builder
    {
        return Invoice::query()
            ->where('type', InvoiceType::Invoice)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Viewed, InvoiceStatus::Partial]);
    }
}
