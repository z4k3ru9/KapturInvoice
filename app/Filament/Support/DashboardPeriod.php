<?php

namespace App\Filament\Support;

use Illuminate\Support\Carbon;

/**
 * Resolves the Dashboard's period filter (see App\Filament\Pages\Dashboard)
 * into a concrete date range + bucket size, shared by every widget that
 * reads `$this->pageFilters` (RevenueOverview, RevenueTrendChart) so they
 * never disagree about what "this period" means.
 */
class DashboardPeriod
{
    /** @var array<string, string> */
    public const PERIODS = [
        'this_week' => 'This week',
        'this_month' => 'This month',
        'this_year' => 'This year',
        'last_year' => 'Last year',
        'custom' => 'Custom range',
    ];

    /**
     * @param  array<string, mixed>|null  $filters
     * @return array{start: Carbon, end: Carbon, group_by: string, label: string}
     */
    public static function resolve(?array $filters): array
    {
        $period = $filters['period'] ?? 'this_month';

        return match ($period) {
            'this_week' => self::range(now()->startOfWeek(), now()->endOfWeek(), 'day', 'This week'),
            'this_year' => self::range(now()->startOfYear(), now()->endOfYear(), 'month', 'This year'),
            'last_year' => self::range(now()->subYearNoOverflow()->startOfYear(), now()->subYearNoOverflow()->endOfYear(), 'month', 'Last year'),
            'custom' => self::custom($filters),
            default => self::range(now()->startOfMonth(), now()->endOfMonth(), 'day', 'This month'),
        };
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return array{start: Carbon, end: Carbon, group_by: string, label: string}
     */
    protected static function custom(?array $filters): array
    {
        $start = filled($filters['start_date'] ?? null)
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : now()->startOfMonth();
        $end = filled($filters['end_date'] ?? null)
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : now()->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->endOfDay(), $start->copy()->startOfDay()];
        }

        $groupBy = $start->diffInDays($end) > 60 ? 'month' : 'day';

        return self::range($start, $end, $groupBy, 'Custom range');
    }

    protected static function range(Carbon $start, Carbon $end, string $groupBy, string $label): array
    {
        return ['start' => $start, 'end' => $end, 'group_by' => $groupBy, 'label' => $label];
    }
}
