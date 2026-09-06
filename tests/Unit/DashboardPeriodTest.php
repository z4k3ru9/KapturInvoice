<?php

namespace Tests\Unit;

use App\Filament\Support\DashboardPeriod;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardPeriodTest extends TestCase
{
    public function test_this_month_is_the_default(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        $period = DashboardPeriod::resolve(null);

        $this->assertSame('2026-09-01', $period['start']->toDateString());
        $this->assertSame('2026-09-30', $period['end']->toDateString());
        $this->assertSame('day', $period['group_by']);

        Carbon::setTestNow();
    }

    public function test_this_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-16')); // a Wednesday

        $period = DashboardPeriod::resolve(['period' => 'this_week']);

        $this->assertTrue($period['start']->lte(Carbon::parse('2026-09-16')));
        $this->assertTrue($period['end']->gte(Carbon::parse('2026-09-16')));
        $this->assertSame('day', $period['group_by']);

        Carbon::setTestNow();
    }

    public function test_this_year_and_last_year_group_by_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        $thisYear = DashboardPeriod::resolve(['period' => 'this_year']);
        $this->assertSame('2026-01-01', $thisYear['start']->toDateString());
        $this->assertSame('month', $thisYear['group_by']);

        $lastYear = DashboardPeriod::resolve(['period' => 'last_year']);
        $this->assertSame('2025-01-01', $lastYear['start']->toDateString());
        $this->assertSame('2025-12-31', $lastYear['end']->toDateString());
        $this->assertSame('month', $lastYear['group_by']);

        Carbon::setTestNow();
    }

    public function test_custom_range_groups_by_day_when_short_and_month_when_long(): void
    {
        $short = DashboardPeriod::resolve([
            'period' => 'custom',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-10',
        ]);
        $this->assertSame('day', $short['group_by']);

        $long = DashboardPeriod::resolve([
            'period' => 'custom',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $this->assertSame('month', $long['group_by']);
    }

    public function test_custom_range_swaps_a_reversed_start_and_end(): void
    {
        $period = DashboardPeriod::resolve([
            'period' => 'custom',
            'start_date' => '2026-06-30',
            'end_date' => '2026-06-01',
        ]);

        $this->assertSame('2026-06-01', $period['start']->toDateString());
        $this->assertSame('2026-06-30', $period['end']->toDateString());
    }
}
