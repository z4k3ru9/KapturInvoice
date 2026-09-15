<?php

namespace App\Filament\Pages;

use App\Filament\Support\DashboardPeriod;
use App\Filament\Widgets\ActionQueueWidget;
use App\Filament\Widgets\ExpiringQuotationsWidget;
use App\Filament\Widgets\RevenueOverview;
use App\Filament\Widgets\RevenueTrendChart;
use App\Filament\Widgets\RevenueTrendTable;
use App\Filament\Widgets\SetupChecklistWidget;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Replaces Filament's stock dashboard so the welcome/stats screen after
 * login shows something real (revenue/outstanding/overdue/open quotations/
 * active jobs + a trend chart + expiring quotations + a role-aware action
 * queue — see docs/filament-admin-layout-design.md §9) instead of just the
 * framework info card. The period filter here is shared by every widget
 * via `Filament\Widgets\Concerns\InteractsWithPageFilters` +
 * `App\Filament\Support\DashboardPeriod::resolve($this->pageFilters)`.
 */
class Dashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->components([
                        Select::make('period')
                            ->label('Period')
                            ->options(DashboardPeriod::PERIODS)
                            ->default('this_month')
                            ->live()
                            ->selectablePlaceholder(false)
                            ->inlineLabel(),
                        DatePicker::make('start_date')
                            ->label('From')
                            ->visible(fn (Get $get) => $get('period') === 'custom'),
                        DatePicker::make('end_date')
                            ->label('To')
                            ->visible(fn (Get $get) => $get('period') === 'custom'),
                    ]),
            ]);
    }

    public function getColumns(): int|array
    {
        return 6;
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            SetupChecklistWidget::class,
            RevenueOverview::class,
            RevenueTrendChart::class,
            RevenueTrendTable::class,
            ExpiringQuotationsWidget::class,
            ActionQueueWidget::class,
        ];
    }
}
