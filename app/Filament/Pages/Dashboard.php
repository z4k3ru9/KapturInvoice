<?php

namespace App\Filament\Pages;

use App\Filament\Support\DashboardPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Replaces Filament's stock dashboard so the welcome/stats screen after
 * login shows something real (revenue/pending/overdue + a trend chart +
 * upcoming quote expiries — see
 * docs/filament-admin-layout-design.md §9) instead of just the framework
 * info card. The period filter here is shared by every widget via
 * `Filament\Widgets\Concerns\InteractsWithPageFilters` +
 * `App\Filament\Support\DashboardPeriod::resolve($this->pageFilters)`.
 */
class Dashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('period')
                    ->label('Period')
                    ->options(DashboardPeriod::PERIODS)
                    ->default('this_month')
                    ->live()
                    ->selectablePlaceholder(false),
                DatePicker::make('start_date')
                    ->label('From')
                    ->visible(fn (Get $get) => $get('period') === 'custom'),
                DatePicker::make('end_date')
                    ->label('To')
                    ->visible(fn (Get $get) => $get('period') === 'custom'),
            ]);
    }
}
