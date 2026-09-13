<?php

namespace App\Filament\Resources\VendorPurchaseOrders;

use App\Filament\Resources\VendorPurchaseOrders\Pages\CreateVendorPurchaseOrder;
use App\Filament\Resources\VendorPurchaseOrders\Pages\EditVendorPurchaseOrder;
use App\Filament\Resources\VendorPurchaseOrders\Pages\ListVendorPurchaseOrders;
use App\Filament\Resources\VendorPurchaseOrders\Pages\ViewVendorPurchaseOrder;
use App\Filament\Resources\VendorPurchaseOrders\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\VendorPurchaseOrders\RelationManagers\VariancesRelationManager;
use App\Filament\Resources\VendorPurchaseOrders\Schemas\VendorPurchaseOrderForm;
use App\Filament\Resources\VendorPurchaseOrders\Schemas\VendorPurchaseOrderInfolist;
use App\Filament\Resources\VendorPurchaseOrders\Tables\VendorPurchaseOrdersTable;
use App\Models\VendorPurchaseOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Phase 05 (docs/rebuild/specs/05-procurement-and-delivery) — a vendor
 * purchase order, the payment ceiling for every VendorBill raised against
 * it (App\Models\VendorPurchaseOrder::paymentCeiling()).
 */
class VendorPurchaseOrderResource extends Resource
{
    protected static ?string $model = VendorPurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Procurement';

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return VendorPurchaseOrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VendorPurchaseOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorPurchaseOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            VariancesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorPurchaseOrders::route('/'),
            'create' => CreateVendorPurchaseOrder::route('/create'),
            'view' => ViewVendorPurchaseOrder::route('/{record}'),
            'edit' => EditVendorPurchaseOrder::route('/{record}/edit'),
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
