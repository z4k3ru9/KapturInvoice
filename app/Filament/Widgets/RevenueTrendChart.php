<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Filament\Support\DashboardPeriod;
use App\Models\Payment;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * The revenue trend line — see docs/filament-admin-layout-design.md §9.
 * Bucketed by day for a week/month-sized window, by month for a
 * year-sized one (`DashboardPeriod` decides which), summing completed
 * payments per bucket.
 */
class RevenueTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Revenue trend';

    protected int|string|array $columnSpan = 'full';

    // See RevenueOverview's $isLazy for why.
    protected static bool $isLazy = false;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $period = DashboardPeriod::resolve($this->pageFilters);

        $labels = [];
        $values = [];

        if ($period['group_by'] === 'month') {
            $cursor = $period['start']->copy()->startOfMonth();

            while ($cursor->lte($period['end'])) {
                $labels[] = $cursor->format('M Y');
                $values[] = (float) Payment::query()
                    ->where('status', PaymentStatus::Completed)
                    ->whereYear('payment_date', $cursor->year)
                    ->whereMonth('payment_date', $cursor->month)
                    ->sum('amount');

                $cursor->addMonth();
            }
        } else {
            $cursor = $period['start']->copy()->startOfDay();

            while ($cursor->lte($period['end'])) {
                $labels[] = $cursor->format('M j');
                $values[] = (float) Payment::query()
                    ->where('status', PaymentStatus::Completed)
                    ->whereDate('payment_date', $cursor->toDateString())
                    ->sum('amount');

                $cursor->addDay();
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $values,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
