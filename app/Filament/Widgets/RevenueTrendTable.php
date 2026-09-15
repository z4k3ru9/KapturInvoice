<?php

namespace App\Filament\Widgets;

use App\Filament\Support\DashboardPeriod;
use App\Filament\Support\Money;
use App\Filament\Support\RevenueBuckets;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * The accessible, screen-reader/no-JS alternative to RevenueTrendChart —
 * DESIGN.md's WCAG 2.2 AA requirement means a chart alone can never be the
 * only way to read this data. Shares the exact same bucket computation
 * (RevenueBuckets) so the two never disagree with each other.
 */
class RevenueTrendTable extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.revenue-trend-table';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /**
     * @return array{rows: list<array{label: string, invoiced: string, collected: string, variance: string}>}
     */
    protected function getViewData(): array
    {
        $currency = Filament::getTenant()?->currency_code;
        $period = DashboardPeriod::resolve($this->pageFilters);
        $buckets = RevenueBuckets::forPeriod($period);

        $rows = [];

        foreach ($buckets['labels'] as $i => $label) {
            $invoiced = $buckets['invoiced'][$i];
            $collected = $buckets['collected'][$i];

            $rows[] = [
                'label' => $label,
                'invoiced' => Money::format($invoiced, $currency),
                'collected' => Money::format($collected, $currency),
                'variance' => Money::format($invoiced - $collected, $currency),
            ];
        }

        return ['rows' => $rows];
    }
}
