<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Filament\Support\DashboardPeriod;
use App\Filament\Support\Money;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * The welcome-page stats row — see
 * docs/rebuild/outputs/18-stitch-ui-gap-analysis/01-shell-dashboard.md D2/
 * D4/D17. "Total revenue" respects the Dashboard's period filter (payments
 * received in that window) and carries a period-over-period delta;
 * "Outstanding balance"/"Overdue invoices"/"Open quotations"/"Active jobs"
 * are deliberately *not* period-filtered — they're a live snapshot of what
 * needs attention right now, same as InvoiceNinja's own dashboard, so a
 * delta against a "previous period" would be meaningless for them.
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
        $currency = $company?->currency_code;
        $period = DashboardPeriod::resolve($this->pageFilters);
        $previous = DashboardPeriod::previous($period);

        $revenue = (float) Payment::query()
            ->where('status', PaymentStatus::Completed)
            ->whereBetween('payment_date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->sum('amount');

        $previousRevenue = (float) Payment::query()
            ->where('status', PaymentStatus::Completed)
            ->whereBetween('payment_date', [$previous['start']->toDateString(), $previous['end']->toDateString()])
            ->sum('amount');

        $outstanding = $this->openInvoicesQuery();
        $overdue = $this->openInvoicesQuery()->where('due_date', '<', today());

        $openQuotations = Quotation::query()
            ->whereIn('status', [QuotationStatus::Approved, QuotationStatus::Sent])
            ->count();

        $activeJobs = SalesOrder::query()
            ->whereIn('status', [
                SalesOrderStatus::Approved,
                SalesOrderStatus::Procurement,
                SalesOrderStatus::InProgress,
                SalesOrderStatus::Delivered,
                SalesOrderStatus::HandedOver,
            ])
            ->count();

        return [
            Stat::make('Total revenue', Money::format($revenue, $currency))
                ->description($period['label'])
                ->descriptionIcon($this->trendIcon($revenue, $previousRevenue))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color($this->trendColor($revenue, $previousRevenue)),
            Stat::make('Outstanding balance', Money::format((clone $outstanding)->sum('balance'), $currency))
                ->description((clone $outstanding)->count().' invoices')
                ->icon(Heroicon::OutlinedClock)
                ->color('warning'),
            Stat::make('Overdue invoices', (clone $overdue)->count())
                ->description(Money::format((clone $overdue)->sum('balance'), $currency).' overdue')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger'),
            Stat::make('Open quotations', $openQuotations)
                ->description('Approved or sent, awaiting a decision')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('info'),
            Stat::make('Active jobs', $activeJobs)
                ->description('In progress toward delivery')
                ->icon(Heroicon::OutlinedBriefcase)
                ->color('info'),
        ];
    }

    protected function openInvoicesQuery(): Builder
    {
        return Invoice::query()
            ->where('type', InvoiceType::Invoice)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Viewed, InvoiceStatus::Partial]);
    }

    /**
     * No meaningful "previous period" when both are zero — omit the trend
     * rather than fabricate a direction (see D4).
     */
    protected function trendIcon(float $current, float $previous): ?Heroicon
    {
        if ($current === 0.0 && $previous === 0.0) {
            return null;
        }

        return $current >= $previous ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown;
    }

    protected function trendColor(float $current, float $previous): string
    {
        if ($current === 0.0 && $previous === 0.0) {
            return 'gray';
        }

        return $current >= $previous ? 'success' : 'danger';
    }
}
