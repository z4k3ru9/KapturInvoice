<?php

namespace App\Support\Dashboard;

use Illuminate\Support\Carbon;

/**
 * Resolves the Dashboard's period filter (see App\Livewire\TallStackDashboard)
 * into a concrete date range + bucket size, shared by every dashboard
 * surface that reads a `period` filter so they never disagree about what
 * "this period" means.
 */
class DashboardPeriod
{
    /** @var array<string, string> */
    public const PERIODS = [
        'this_week' => 'This week',
        'this_month' => 'This month',
        'this_quarter' => 'This quarter',
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
            // A quarter (~90 days) follows the same >60-day rule as a long
            // custom range: group by month, not day, so the trend chart
            // doesn't render ~90 daily buckets.
            'this_quarter' => self::range(now()->startOfQuarter(), now()->endOfQuarter(), 'month', 'This quarter'),
            'this_year' => self::range(now()->startOfYear(), now()->endOfYear(), 'month', 'This year'),
            'last_year' => self::range(now()->subYearNoOverflow()->startOfYear(), now()->subYearNoOverflow()->endOfYear(), 'month', 'Last year'),
            'custom' => self::custom($filters),
            default => self::range(now()->startOfMonth(), now()->endOfMonth(), 'day', 'This month'),
        };
    }

    /**
     * The same-length window immediately preceding the given one, used to
     * compute a period-over-period delta (the dashboard's "Total revenue"
     * tile) — see 01-shell-dashboard.md D4. A plain day-count shift rather
     * than a calendar-aware "previous quarter/year" so every period type
     * (including a custom range) gets a comparable window of identical
     * length.
     *
     * @param  array{start: Carbon, end: Carbon, group_by: string, label: string}  $period
     * @return array{start: Carbon, end: Carbon}
     */
    public static function previous(array $period): array
    {
        $days = $period['start']->diffInDays($period['end']) + 1;

        return [
            'start' => $period['start']->copy()->subDays($days),
            'end' => $period['start']->copy()->subDay()->endOfDay(),
        ];
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
