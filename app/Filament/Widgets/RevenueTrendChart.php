<?php

namespace App\Filament\Widgets;

use App\Filament\Support\DashboardPeriod;
use App\Filament\Support\RevenueBuckets;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * The revenue trend chart — see
 * docs/rebuild/outputs/18-stitch-ui-gap-analysis/01-shell-dashboard.md D5.
 * Two series (invoiced vs. collected), bucketed by day for a week/month-
 * sized window or by month for a year/quarter-sized one
 * (`DashboardPeriod` decides which). See RevenueBuckets for the shared
 * query logic and why "Invoiced" is labeled "(legacy)".
 */
class RevenueTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Revenue & cash inflow trend';

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
        $buckets = RevenueBuckets::forPeriod($period);
        $accent = Filament::getTenant()?->primary_color ?: '#E63934';

        return [
            'datasets' => [
                [
                    'label' => 'Invoiced (legacy)',
                    'data' => $buckets['invoiced'],
                    'borderColor' => $accent,
                    'backgroundColor' => $accent.'26',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Cash collected',
                    'data' => $buckets['collected'],
                    'borderColor' => '#465b9d',
                    'backgroundColor' => 'rgba(70, 91, 157, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $buckets['labels'],
        ];
    }
}
