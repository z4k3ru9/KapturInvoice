<?php

namespace App\Filament\Resources\VendorBills;

use App\Filament\Resources\VendorBills\Pages\CreateVendorBill;
use App\Filament\Resources\VendorBills\Pages\EditVendorBill;
use App\Filament\Resources\VendorBills\Pages\ListVendorBills;
use App\Filament\Resources\VendorBills\Pages\ViewVendorBill;
use App\Filament\Resources\VendorBills\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\VendorBills\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\VendorBills\Schemas\VendorBillForm;
use App\Filament\Resources\VendorBills\Schemas\VendorBillInfolist;
use App\Filament\Resources\VendorBills\Tables\VendorBillsTable;
use App\Models\VendorBill;
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
 * bill against an approved Vendor Purchase Order, supporting partial
 * vendor payments and job-cost allocation of its own line items.
 */
class VendorBillResource extends Resource
{
    protected static ?string $model = VendorBill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = 'Procurement';

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return VendorBillForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VendorBillInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorBillsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorBills::route('/'),
            'create' => CreateVendorBill::route('/create'),
            'view' => ViewVendorBill::route('/{record}'),
            'edit' => EditVendorBill::route('/{record}/edit'),
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
