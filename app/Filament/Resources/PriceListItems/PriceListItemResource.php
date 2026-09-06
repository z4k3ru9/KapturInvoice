<?php

namespace App\Filament\Resources\PriceListItems;

use App\Filament\Resources\PriceListItems\Pages\ListPriceListItems;
use App\Filament\Resources\PriceListItems\Pages\ViewPriceListItem;
use App\Filament\Resources\PriceListItems\Schemas\PriceListItemForm;
use App\Filament\Resources\PriceListItems\Schemas\PriceListItemInfolist;
use App\Filament\Resources\PriceListItems\Tables\PriceListItemsTable;
use App\Models\PriceListItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The vendor pricelist reference catalog (Hikvision/HiLook and future
 * brands) — see docs/price-list-import.md. Rows normally arrive via the
 * "Import" header action (App\Services\PriceListImporter), not manual
 * entry, but manual create/edit stays available for one-off additions.
 */
class PriceListItemResource extends Resource
{
    protected static ?string $model = PriceListItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Price List';

    protected static ?string $recordTitleAttribute = 'sku';

    public static function form(Schema $schema): Schema
    {
        return PriceListItemForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PriceListItemInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceListItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    // No 'create'/'edit' pages registered — Filament automatically falls
    // back to a modal for the CreateAction/EditAction already used in
    // ListPriceListItems/this table (see docs/filament-admin-layout-design.md
    // §6); 'view' stays a page.
    public static function getPages(): array
    {
        return [
            'index' => ListPriceListItems::route('/'),
            'view' => ViewPriceListItem::route('/{record}'),
        ];
    }
}
