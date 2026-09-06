<?php

namespace App\Filament\Resources\TaxRates;

use App\Filament\Resources\TaxRates\Pages\ListTaxRates;
use App\Filament\Resources\TaxRates\Pages\ViewTaxRate;
use App\Filament\Resources\TaxRates\Schemas\TaxRateForm;
use App\Filament\Resources\TaxRates\Schemas\TaxRateInfolist;
use App\Filament\Resources\TaxRates\Tables\TaxRatesTable;
use App\Models\TaxRate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class TaxRateResource extends Resource
{
    protected static ?string $model = TaxRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TaxRateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TaxRateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxRatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    // No 'create'/'edit' pages registered — Filament automatically falls
    // back to a modal for the CreateAction/EditAction already used in
    // ListTaxRates/this table and on ViewTaxRate's header (see
    // docs/filament-admin-layout-design.md §6); 'view' stays a page.
    public static function getPages(): array
    {
        return [
            'index' => ListTaxRates::route('/'),
            'view' => ViewTaxRate::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
