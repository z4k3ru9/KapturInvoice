<?php

namespace App\Filament\Resources\SalesOrders;

use App\Filament\Resources\SalesOrders\Pages\ListSalesOrders;
use App\Filament\Resources\SalesOrders\Pages\ViewSalesOrder;
use App\Filament\Resources\SalesOrders\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\SalesOrders\RelationManagers\MilestonesRelationManager;
use App\Filament\Resources\SalesOrders\RelationManagers\VariationsRelationManager;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderInfolist;
use App\Filament\Resources\SalesOrders\Tables\SalesOrdersTable;
use App\Models\SalesOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * The Job aggregate (docs/rebuild/specs/03-sales-and-job/Specs.md) — see
 * App\Models\SalesOrder's docblock and docs/REFACTOR_PLAN.md §2 risk #3
 * for why this is a new resource, never an extension of the frozen
 * ProjectResource. Per the acceptance criteria ("A user can open one job
 * and find the accepted quote, PO state, milestones, future invoices,
 * procurement, delivery, handover, costs, and activity ..."), Items/
 * Milestones/Variations all live as relation managers on the one View
 * page; procurement/delivery/costs are Phase 05 additions to this same
 * resource, not a separate one.
 */
class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $modelLabel = 'Job';

    protected static ?string $recordTitleAttribute = 'number';

    public static function infolist(Schema $schema): Schema
    {
        return SalesOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            MilestonesRelationManager::class,
            VariationsRelationManager::class,
        ];
    }

    // No 'create'/'edit' pages — see App\Actions\Sales\CreateSalesOrderFromQuotation
    // and this resource's docblock.
    public static function getPages(): array
    {
        return [
            'index' => ListSalesOrders::route('/'),
            'view' => ViewSalesOrder::route('/{record}'),
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
